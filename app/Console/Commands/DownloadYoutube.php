<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Entities\CURLFile;


class DownloadYoutube extends Command
{
    protected $signature = 'youtube:download {url} {--format=mp4} {--resolution=720} {--audio-only}';
    protected $description = 'Download YouTube videos or get video info';

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

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[count($matches) - 1];
            }
        }

        return null;
    }

    private function downloadVideo($videoId, $format, $resolution, $audioOnly)
    {
        /*
        $bot_api_key = '8030468571:AAFhSaw8zoiwxBZhf14BsL2QI9iQ8N_HKUE';  // 您的 Telegram Bot Token
        $chat_id = '-4664862172'; // 您的聊天 ID

        // 設定 Telegram Bot
        $telegram = new Telegram($bot_api_key);

        // // 轉換檔案為 CURLFile 物件
        // $document = new \CURLFile(realpath('/var/www/Scraper-System/storage/app/youtube/754568409.827326.mp4'), 'video/mp4');
        // dd($document);
        // // 發送檔案
        // $response = Request::sendDocument([
        //     'chat_id' => $chat_id,
        //     'parse_mode' => 'mp4',
        //     'document' => $document,
        // ]);
        //dd($response);
        $bot_api_key = '8030468571:AAFhSaw8zoiwxBZhf14BsL2QI9iQ8N_HKUE';  // 您的 Telegram Bot Token
        $chat_id = '-4664862172'; // 您的聊天 ID

        // 使用 Laravel HTTP 客戶端發送檔案
        $response = Http::timeout(6000)  // 設定超時時間為 60 秒
            ->attach(
                'document',
                file_get_contents('/var/www/Scraper-System/storage/app/youtube/Rick Astley - Never Gonna Give You Up (Official Music Video)-720p.mp4'),
                'Rick Astley - Never Gonna Give You Up (Official Music Video)-720p.mp4'
            )
            ->post('https://api.telegram.org/bot' . $bot_api_key . '/sendDocument', [
                'chat_id' => $chat_id,
            ]);

        if ($response->successful()) {
            echo "檔案上傳成功";
        } else {
            echo "檔案上傳失敗：" . $response->body();
        }


        // if ($response->isOk()) {
        //     echo "File uploaded successfully!";
        // } else {
        //     echo "Error uploading file: " . $response->getDescription();
        // }

        */
        //dd(213123);
        // 使用 yt-dlp 工具執行下載
        //$ytDlpPath = '/usr/local/bin/yt-dlp'; // yt-dlp 工具路徑
        $ytDlpPath = '/home/vagrant/.local/bin/yt-dlp';
        $url = 'https://www.youtube.com/watch?v=' . $videoId;
        $outputPath = storage_path('app/youtube/%(title)s-720p.%(ext)s');
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

        if ($returnVar === 0) {
            echo "Download successful! Video saved to:\n";
            echo $outputPath . "\n";

            // 獲取下載的檔案名稱並轉換為 .mp4
            $downloadedFile = glob(storage_path('app/youtube/*-720p.*'))[0]; // 假設只會有一個文件
            $convertedFile = preg_replace('/\.[^.]+$/', '.mp4', $downloadedFile); // 轉換檔案名稱為 .mp4

            // 檢查檔案是否為 webm 格式，並使用 ffmpeg 進行轉換
            if (pathinfo($downloadedFile, PATHINFO_EXTENSION) === 'webm') {
                $ffmpegCommand = sprintf(
                    'ffmpeg -i %s -c:v libx264 -c:a aac -strict experimental %s',
                    escapeshellarg($downloadedFile),
                    escapeshellarg($convertedFile)
                );

                exec($ffmpegCommand, $output, $returnVar);

                if ($returnVar === 0) {
                    echo "Video converted to MP4 successfully! Saved to:\n";
                    echo $convertedFile . "\n";

                    // 可選擇刪除原始的 webm 檔案
                    unlink($downloadedFile); // 刪除原始的 webm 檔案
                    echo "Original webm file deleted.\n";

                    $this->uploadToTelegram();
                } else {
                    echo "Conversion failed. Error:\n";
                    echo implode("\n", $output);
                }
            } else {
                echo "Downloaded file is not in webm format.\n";
            }
        } else {
            echo "Download failed. Error:\n";
            echo implode("\n", $output);
        }
    }

    public function uploadToTelegram()
    {
        $bot_api_key = '8030468571:AAFhSaw8zoiwxBZhf14BsL2QI9iQ8N_HKUE';  // 您的 Telegram Bot Token
        $chat_id = '-4664862172'; // 您的聊天 ID

        // 設定 Telegram Bot
        $telegram = new Telegram($bot_api_key);

        // // 轉換檔案為 CURLFile 物件
        // $document = new \CURLFile(realpath('/var/www/Scraper-System/storage/app/youtube/754568409.827326.mp4'), 'video/mp4');
        // dd($document);
        // // 發送檔案
        // $response = Request::sendDocument([
        //     'chat_id' => $chat_id,
        //     'parse_mode' => 'mp4',
        //     'document' => $document,
        // ]);
        //dd($response);
        $bot_api_key = '8030468571:AAFhSaw8zoiwxBZhf14BsL2QI9iQ8N_HKUE';  // 您的 Telegram Bot Token
        $chat_id = '-4664862172'; // 您的聊天 ID

        // 使用 Laravel HTTP 客戶端發送檔案
        $response = Http::timeout(6000)  // 設定超時時間為 60 秒
            ->attach(
                'document',
                file_get_contents('/var/www/Scraper-System/storage/app/youtube/Rick Astley - Never Gonna Give You Up (Official Music Video)-720p.mp4'),
                'Rick Astley - Never Gonna Give You Up (Official Music Video)-720p.mp4'
            )
            ->post('https://api.telegram.org/bot' . $bot_api_key . '/sendDocument', [
                'chat_id' => $chat_id,
            ]);

        if ($response->successful()) {
            echo "檔案上傳成功";
        } else {
            echo "檔案上傳失敗：" . $response->body();
        }
    }
}
