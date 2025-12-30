<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class DownloadYoutube extends Command
{
    protected $signature = 'youtube:download
        {url : YouTube video URL}
        {--audio-only : Download audio only (mp3)}';

    protected $description = 'Download YouTube video using static yt-dlp + ffmpeg';

    protected string $basePath;
    protected $telegramService;
    protected string $chatId = '-4661576369';

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->basePath = storage_path('app/youtube');
        $this->telegramService = $telegramService;
    }

    public function handle()
    {
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
    }


    private function downloadVideo(string $url): int
    {
        $command = sprintf(
            '%s --cookies %s --sleep-requests 3 --min-sleep-interval 2 --max-sleep-interval 6 --concurrent-fragments 1 -f "bv*+ba/b" --merge-output-format mp4 -o %s %s',
            escapeshellcmd($this->ytDlpPath()),
            escapeshellarg($this->cookiesPath()),
            escapeshellarg($this->outputTemplate()),
            escapeshellarg($url)
        );

        return $this->runAndSend($command, 'mp4');
    }

    private function downloadAudio(string $url): int
    {
        $command = sprintf(
            '%s --cookies %s -x --audio-format mp3 -o %s %s',
            escapeshellcmd($this->ytDlpPath()),
            escapeshellarg($this->cookiesPath()),
            escapeshellarg($this->outputTemplate()),
            escapeshellarg($url)
        );

        return $this->runAndSend($command, 'mp3');
    }

    /* -------------------------------------------------
     | Execute + Telegram
     |--------------------------------------------------*/

    private function runAndSend(string $command, string $ext): int
    {
        try {
            exec($command, $output, $code);

            Log::info('yt-dlp executed', [
                'command' => $command,
                'exit_code' => $code,
                'output' => $output,
            ]);

            if ($code !== 0) {
                throw new \RuntimeException('yt-dlp exit code != 0');
            }

            $file = $this->latestFile($ext);
            if (!$file) {
                throw new \RuntimeException('Downloaded file not found');
            }

            $filename = $file->getFilename();
            $filepath = $file->getPathname();

            Log::info('File ready for telegram', [
                'file' => $filename,
                'size_mb' => round(filesize($filepath) / 1024 / 1024, 2),
            ]);

            // 嘗試傳送 Telegram
            $this->telegramService->sendMessage(
                $this->chatId,
                '下載完成：' . pathinfo($filename, PATHINFO_FILENAME)
            );

            $this->telegramService->sendDocument(
                $this->chatId,
                $filepath,
                $filename
            );

            Log::info('Telegram sendDocument called');

            // 只在「確認沒丟例外」後才刪檔
            File::delete($filepath);

            return 0;

        } catch (\Throwable $e) {

            Log::error('DownloadYoutube failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 回報 Telegram（避免你以為沒發生）
            try {
                $this->telegramService->sendMessage(
                    $this->chatId,
                    "❌ 發生錯誤：\n" . $e->getMessage()
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
}
