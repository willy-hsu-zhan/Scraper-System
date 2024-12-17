<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use App\Services\TelegramService;

class DownloadYoutube extends Command
{
    protected $signature = 'youtube:download {url} {--format=mp4} {--resolution=720} {--audio-only}';
    protected $description = 'Download YouTube videos or get video info';

    protected $fileName;
    protected $telegramService;
    protected $chatId = '-4664862172'; // 暫時先用全域變數 // 日後聊天室擴充再更改
    protected $baseFilePath;
    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->baseFilePath = (string)storage_path('app/youtube/');
    }

    public function handle()
    {
        $url = $this->argument('url');
        $format = $this->option('format');
        $resolution = $this->option('resolution');
        $audioOnly = $this->option('audio-only');

        $this->info("Processing URL: $url");

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

    private function doFormatWebmToMp4Process()
    {
        $downloadedFile = $this->getDownloadFile();
        $convertedFile = preg_replace('/\.[^.]+$/', '.mp4', $downloadedFile); // 轉換檔案名稱為 .mp4
        // 檢查檔案是否為 webm 格式，並使用 ffmpeg 進行轉換
        if (pathinfo($downloadedFile, PATHINFO_EXTENSION) === 'webm')
        {
            $ffmpegCommand = sprintf(
                'ffmpeg -i %s -c:v libx264 -c:a aac -strict experimental %s',
                escapeshellarg($downloadedFile),
                escapeshellarg($convertedFile)
            );

            exec($ffmpegCommand, $output, $returnVar);

            if ($returnVar === 0)
            {
                echo "Video converted to MP4 successfully! Saved to:\n";
                echo $convertedFile . "\n";

                // 可選擇刪除原始的 webm 檔案
                unlink($downloadedFile); // 刪除原始的 webm 檔案
                echo "Original webm file deleted.\n";
                $this->fileName = basename($convertedFile);
            }
            else
            {
                echo "Conversion failed. Error:\n";
                echo implode("\n", $output);
                return 1;
            }
        }
        else
        {
            echo "Downloaded file is not in webm format.\n";
            return 1;
        }
    }

    private function getDownloadFile()
    {
        // 獲取下載的檔案名稱並轉換為 .mp4
        return glob(storage_path('app/youtube/*-720p.*'))[0]; // 假設只會有一個文件
    }

    private function downloadVideo($videoId, $format, $resolution, $audioOnly)
    {
        $ytDlpPath = $this->getYtDlpPath();
        $url = 'https://www.youtube.com/watch?v=' . $videoId;
        $outputPath = $this->getOutputPath();
        $format = 'bestvideo[height<=720]+bestaudio'; // 下載最高畫質影片

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
            echo $outputPath . "\n";
            $this->doFormatWebmToMp4Process();
        }
        else
        {
            echo "Download failed. Error:\n";
            echo implode("\n", $output);
            return 1;
        }

        // upload to telegram bot
        $filePath = $this->baseFilePath . $this->fileName;
        $this->telegramService->sendDocument($this->chatId, $filePath, $this->fileName);
    }
}
