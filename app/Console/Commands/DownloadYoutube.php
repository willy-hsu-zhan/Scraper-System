<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DownloadYoutube extends Command
{
    protected $signature = 'youtube:download
        {url : YouTube video URL}
        {--audio-only : Download audio only (mp3)}';

    protected $description = 'Download YouTube video using static yt-dlp + ffmpeg';

    protected string $basePath;
    protected $telegramService;
    protected string $chatId = '-4661576369'; // 影片輸出群
    protected string $chatId2 = '-4661576369'; // 音樂分享群

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->basePath = storage_path('app/youtube');
        $this->telegramService = $telegramService;
    }

    public function handle()
    {
        $this->telegramService->sendMessage(
            $this->chatId2,
            '✅ 已收到下載指令，請至輸出群查看'
        );

        $lock = Cache::lock('youtube:downloading', 120); // 最多鎖 2 分鐘

        if (!$lock->get()) {
            $this->telegramService->sendMessage(
                $this->chatId,
                '⏳ 目前已有下載任務進行中，請稍後再試'
            );
            return 0;
        }

        try {
            $url = $this->argument('url');
            $audioOnly = $this->option('audio-only');

            if (!$this->isValidYoutubeUrl($url)) {
                $this->error('Invalid YouTube URL');
                return 1;
            }

            File::ensureDirectoryExists($this->basePath);

            return $audioOnly
                ? $this->downloadAudio($url)
                : $this->downloadVideo($url);

        } finally {
            optional($lock)->release();
        }
    }

    private function downloadVideo(string $url): int
    {
        // 1️⃣ 原畫質
        if ($this->tryDownloadVideo($url, '原畫質', 'bv*+ba/b')) {
            return 0;
        }

        // 2️⃣ 480p
        if ($this->tryDownloadVideo(
            $url,
            '480p',
            'bv*[height<=480]+ba/b'
        )) {
            return 0;
        }

        // 3️⃣ 360p
        if ($this->tryDownloadVideo(
            $url,
            '360p',
            'bv*[height<=360]+ba/b'
        )) {
            return 0;
        }

        // 4️⃣ 全部失敗 → 自動改成音樂
        $this->telegramService->sendMessage(
            $this->chatId,
            "🎧 影片即使降到 360p 仍過大\n已自動改為下載音樂（mp3）"
        );

        return $this->downloadAudio($url);
    }

    private function downloadAudio(string $url): int
    {
        $command = sprintf(
            '%s --extractor-args "youtube:player_client=android" ' .
            '--cookies %s -x --audio-format mp3 -o %s %s',
            escapeshellcmd($this->ytDlpPath()),
            escapeshellarg($this->cookiesPath()),
            escapeshellarg($this->outputTemplate()),
            escapeshellarg($url)
        );

        return $this->runAndSend($command, 'mp3');
    }

    private function runAndSend(string $command, string $ext): int
    {
        try {
            $process = proc_open(
                $command,
                [
                    1 => ['pipe', 'w'], // stdout
                    2 => ['pipe', 'w'], // stderr
                ],
                $pipes
            );

            if (!is_resource($process)) {
                throw new \RuntimeException('Failed to start yt-dlp process');
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            Log::info('yt-dlp executed', [
                'command'   => $command,
                'exit_code'=> $exitCode,
                'stdout'   => $stdout,
                'stderr'   => $stderr,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    "yt-dlp failed\n" .
                    "ExitCode: {$exitCode}\n" .
                    "Error:\n{$stderr}"
                );
            }

            $file = $this->latestFile($ext);
            if (!$file) {
                throw new \RuntimeException('Downloaded file not found');
            }

            $filename = $file->getFilename();
            $filepath = $file->getPathname();

            $sizeMB = round(filesize($filepath) / 1024 / 1024, 2);

            $this->telegramService->sendMessage(
                $this->chatId,
                "下載完成：".pathinfo($filename, PATHINFO_FILENAME).
                "\n檔案大小：{$sizeMB} MB\n準備上傳..."
            );

            try {
                $this->telegramService->sendDocument(
                    $this->chatId,
                    $filepath,
                    $filename
                );

                $this->telegramService->sendMessage(
                    $this->chatId,
                    "✅ 檔案上傳完成"
                );

                File::delete($filepath);
                return 0;

            } catch (\Throwable $e) {

                Log::error('Telegram sendDocument failed', [
                    'file'  => $filename,
                    'error' => $e->getMessage(),
                ]);

                // 使用者一定要知道發生什麼事
                $this->telegramService->sendMessage(
                    $this->chatId,
                    "⚠️ 檔案已下載完成，但 Telegram 傳送失敗\n".
                    "原因：{$e->getMessage()}\n".
                    "檔名：{$filename}"
                );

                // 不刪檔，避免使用者要重試卻找不到
                return 1;
            }

        } catch (\Throwable $e) {

            Log::error('DownloadYoutube failed', [
                'error' => $e->getMessage(),
            ]);

            try {
                $this->telegramService->sendMessage(
                    $this->chatId,
                    "❌ yt-dlp 下載失敗\n\n".$e->getMessage()
                );
            } catch (\Throwable $inner) {
                Log::error('Telegram error notify failed', [
                    'error' => $inner->getMessage(),
                ]);
            }

            return 1;
        }
    }


    private function latestFile(string $ext)
    {
        return collect(File::files($this->basePath))
            ->filter(fn($f) => $f->getExtension() === $ext)
            ->sortByDesc(fn($f) => $f->getMTime())
            ->first();
    }

    private function outputTemplate(): string
    {
        return $this->basePath . '/%(title)s.%(ext)s';
    }

    private function ytDlpPath(): string
    {
        return '/usr/local/bin/yt-dlp';
    }

    private function cookiesPath(): string
    {
        return $this->basePath . '/cookies.txt';
    }

    private function isValidYoutubeUrl(string $url): bool
    {
        return preg_match('/(youtube\.com|youtu\.be)/', $url) === 1;
    }

    private function tryDownloadVideo(string $url, string $formatLabel, string $formatExpr): bool
    {
        $this->clearDirectory();

        $this->telegramService->sendMessage(
            $this->chatId,
            "🎬 嘗試下載影片（{$formatLabel}）..."
        );

        $command = sprintf(
            '%s --cookies %s --sleep-requests 3 --min-sleep-interval 2 --max-sleep-interval 6 --concurrent-fragments 1 ' .
            '-f %s --merge-output-format mp4 -o %s %s',
            escapeshellcmd($this->ytDlpPath()),
            escapeshellarg($this->cookiesPath()),
            escapeshellarg($formatExpr),
            escapeshellarg($this->outputTemplate()),
            escapeshellarg($url)
        );

        exec($command, $output, $code);

        if ($code !== 0) {
            Log::warning("Download failed at {$formatLabel}", compact('output'));
            return false;
        }

        $file = $this->latestFile('mp4');
        if (!$file) {
            return false;
        }

        $sizeMB = round(filesize($file->getPathname()) / 1024 / 1024, 2);

        $this->telegramService->sendMessage(
            $this->chatId,
            "📦 {$formatLabel} 下載完成\n檔案大小：{$sizeMB} MB"
        );

        // Telegram 安全上限（保守抓 48MB）
        if ($sizeMB > 48) {
            $this->telegramService->sendMessage(
                $this->chatId,
                "⚠️ 超過 Telegram 限制，嘗試降低畫質..."
            );

            File::delete($file->getPathname());
            return false;
        }

        // 尺寸 OK → 直接上傳這個已下載好的檔案
        return $this->uploadToTelegram($file->getPathname(), $file->getFilename(), $sizeMB) === 0;
    }

    private function clearDirectory(): void
    {
        if (!File::exists($this->basePath)) {
            File::makeDirectory($this->basePath, 0755, true);
            return;
        }

        foreach (File::files($this->basePath) as $file) {
            File::delete($file->getPathname());
        }
    }

    private function uploadToTelegram(string $filepath, string $filename, float $sizeMB): int
    {
        $this->telegramService->sendMessage(
            $this->chatId,
            '下載完成：' . pathinfo($filename, PATHINFO_FILENAME) .
            "\n檔案大小：{$sizeMB} MB" .
            "\n準備上傳..."
        );

        try {
            $this->telegramService->sendDocument($this->chatId, $filepath, $filename);

            $this->telegramService->sendMessage($this->chatId, "✅ 檔案上傳完成");

            Log::info('Telegram sendDocument success', [
                'file' => $filename,
                'size_mb' => $sizeMB,
            ]);

            File::delete($filepath);
            return 0;

        } catch (\Throwable $e) {
            Log::error('Telegram sendDocument failed', [
                'file'  => $filename,
                'error' => $e->getMessage(),
            ]);

            $this->telegramService->sendMessage(
                $this->chatId,
                "⚠️ 檔案已下載完成，但 Telegram 傳送失敗\n".
                "原因：{$e->getMessage()}\n".
                "檔名：{$filename}"
            );

            // 不刪檔
            return 1;
        }
    }

}
