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
        if (!$videoId) {
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
        // 使用 yt-dlp 工具執行下載
        //$ytDlpPath = '/usr/local/bin/yt-dlp'; // yt-dlp 工具路徑
        // 使用 yt-dlp 工具執行下載
        $ytDlpPath = '/home/vagrant/.local/bin/yt-dlp'; // 更新 yt-dlp 工具路徑
        $outputDir = storage_path('app/youtube');
        $fileName = $audioOnly ? '%(title)s.%(ext)s' : "%(title)s-${resolution}p.%(ext)s";

        $command = "$ytDlpPath -f ";
        $command .= $audioOnly ? "bestaudio" : "bestvideo[height<=$resolution]+bestaudio";
        $command .= " -o \"$outputDir/$fileName\" https://www.youtube.com/watch?v=$videoId";

        $this->info("Running command: $command");
        exec($command, $output, $status);

        if ($status === 0) {
            $this->info("Download completed. Files saved in: $outputDir");
        } else {
            $this->error("Download failed. Error: " . implode("\n", $output));
        }
    }
}
