<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class DownloadYoutube extends Command
{
    protected $signature = 'youtube:download {url?} {--format=mp4} {--resolution=720} {--audio-only}';
    protected $description = 'Download YouTube videos or get video info';

    protected $fileName;
    protected $telegramService;
    protected $chatId = '-4661576369'; // 暫時先用全域變數 // 日後聊天室擴充再更改 音樂群
    protected $baseFilePath;

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->baseFilePath = storage_path('app/youtube/');
    }

    public function handle()
    {
        // 先清所有檔案
        $this->deleteAllTempFiles();

        $url = $this->argument('url');
        $format = $this->option('format');
        $resolution = $this->option('resolution');
        $audioOnly = $this->option('audio-only');

        $this->info("Processing URL: $url");
        Log::info("Processing URL: $url");

        // 解析 YouTube URL
        $videoId = $this->getVideoId($url);
        if (!$videoId)
        {
            $this->error('Invalid YouTube URL.');
            return 1;
        }

        $this->info("Extracted Video ID: $videoId");

        // 處理下載或資訊
        $this->downloadVideo($videoId, $format, $resolution, $audioOnly);

        return 0;
    }

    private function getVideoId($url)
    {
        $patterns = [
            '/youtu\.be\/([0-9A-Za-z-_]{10,})/',
            '/[?&]v=([0-9A-Za-z-_]{10,})/',
            '/\/(v|embed|shorts|live)\/([0-9A-Za-z-_]{10,})/',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $url, $matches))
            {
                return $matches[count($matches) - 1];
            }
        }

        return null;
    }

    private function getYtDlpPath() // 本地預設抓homestead路徑 正式環境另抓位置
    {
        return app('env') == 'local' ? '/home/vagrant/.local/bin/yt-dlp' : '/usr/local/bin/yt-dlp';
    }

    private function getOutputPath()
    {
        return $this->baseFilePath . '%(title)s-720p.%(ext)s';
    }

    private function deleteAllTempFiles()
    {
        $directory = $this->baseFilePath;

        // 先檢查目錄是否存在，存在的話刪除
        if (File::exists($directory))
        {
            File::deleteDirectory($directory); // 刪除整個資料夾
        }

        // 重新建立空資料夾，如果資料夾不存在的話
        if (!File::exists($directory))
        {
            File::makeDirectory($directory, 0755, true); // 重新建立目錄，確保目錄結構
        }

        sleep(2); // 刪除檔案預留2秒處理
    }


    private function deleteTempFiles()
    {
        // 確認檔案是否存在，並刪除指定的檔案
        $filePath = $this->baseFilePath . $this->fileName;

        if (File::exists($filePath)) {
            File::delete($filePath); // 刪除單一檔案
        }

        sleep(2); // 刪除檔案預留10秒處理
    }

    private function downloadVideo($videoId, $format, $resolution, $audioOnly)
    {
        $ytDlpPath = $this->getYtDlpPath();
        $url = 'https://www.youtube.com/watch?v=' . $videoId;
        $outputPath = $this->getOutputPath();
        $format = 'bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]';  // 強制選擇 mp4 格式 // 下載最高畫質影片

        // 使用 escapeshellarg 處理特殊符號
        $command = sprintf(
            '%s -f %s -o %s %s',
            escapeshellarg($ytDlpPath),
            escapeshellarg($format),
            escapeshellarg($outputPath),
            escapeshellarg($url)
        );

        // 執行下載命令
        exec($command, $output, $returnVar);

        if ($returnVar === 0)
        {
            echo "Download successful! Video saved to:\n";
            Log::info("Download successful! Video saved to:\n");
            echo $outputPath . "\n";
            Log::info($outputPath . "\n");

            // 下載完成後，設定文件名稱
            $this->fileName = basename($outputPath);
        }
        else
        {
            echo "Download failed. Error:\n";
            Log::info("Download failed. Error:\n");
            echo implode("\n", $output);
            Log::info(implode("\n", $output));
            return 1;
        }

        // 檢查 $this->fileName 是否有效
        if (!$this->fileName) {
            $this->error('File name is null or empty!');
            return 1;
        }

        // send msg file name
        $cleanFileName = preg_replace('/720p\.mp4$/', '', $this->fileName); // 移除結尾的 "720p.mp4"
        $text = '歌曲名稱：' . $cleanFileName;
        $this->telegramService->sendMessage($this->chatId, $text);

        // upload to telegram bot
        $filePath = $this->baseFilePath . $this->fileName;
        $this->telegramService->sendDocument($this->chatId, $filePath, $this->fileName);

        sleep(60 * 2); // 等待2分鐘繼續上傳

        // delete all temp files
        $this->deleteTempFiles();
    }
}
