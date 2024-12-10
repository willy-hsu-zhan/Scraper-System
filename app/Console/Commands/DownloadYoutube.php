<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[count($matches) - 1];
            }
        }

        return null;
    }

    private function downloadVideo($videoId, $format, $resolution, $audioOnly)
    {
        //dd($videoId);
        // 使用 yt-dlp 工具執行下載
        //$ytDlpPath = '/usr/local/bin/yt-dlp'; // yt-dlp 工具路徑
        $ytDlpPath = '/home/vagrant/.local/bin/yt-dlp';
        //$url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $url = 'https://www.youtube.com/watch?v=' . $videoId;
        $outputPath = storage_path('app/youtube/%(title)s-720p.%(ext)s');
        $format = 'bestvideo[height<=720]+bestaudio';

        // 使用 escapeshellarg 處理特殊符號
        $command = sprintf(
            '%s -f %s -o %s %s',
            escapeshellarg($ytDlpPath),
            escapeshellarg($format),
            escapeshellarg($outputPath),
            escapeshellarg($url)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0)
        {
            echo "Download successful!";
        }
        else
        {
            echo "Download failed. Error:\n";
            echo implode("\n", $output);
        }
    }
}
