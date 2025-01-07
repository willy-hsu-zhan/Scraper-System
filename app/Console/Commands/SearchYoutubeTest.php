<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SearchYoutubeTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:search-youtube-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiKey = env('YOUTUBE_API_KEY');

        $url = 'https://www.googleapis.com/youtube/v3/videos';

        // 發送 GET 請求
        $response = Http::get($url, [
            'part' => 'snippet,contentDetails,statistics',
            'chart' => 'mostPopular',
            'regionCode' => 'TW',
            'videoCategoryId' => '10',
            'maxResults' => 10,
            'key' => $apiKey,
        ]);

        if ($response->successful()) {
            $videos = $response->json()['items'];

            foreach ($videos as $video) {
                $this->info('---');
                $this->info('標題: ' . $video['snippet']['title']);
                $this->info('頻道名稱: ' . $video['snippet']['channelTitle']);
                $this->info('觀看次數: ' . $video['statistics']['viewCount']);
                $this->info('影片連結: https://www.youtube.com/watch?v=' . $video['id']);
            }
        } else {
            $this->error('Failed to fetch data. Error: ' . $response->status());
        }
    }
}
