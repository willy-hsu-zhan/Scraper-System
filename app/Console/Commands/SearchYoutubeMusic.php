<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\YoutubeVideo;
use Illuminate\Support\Facades\Artisan;

class SearchYoutubeMusic extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:search-youtube-music';

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
            'part'            => 'snippet,contentDetails,statistics',
            'chart'           => 'mostPopular',
            'regionCode'      => 'TW',
            'videoCategoryId' => '10', // 音樂類別
            'maxResults'      => 4, // 限制回傳只有4筆
            'key'             => $apiKey,
        ]);

        if( $response->successful() )
        {
            $videos = $response->json()['items'];

            $recentDate = Carbon::now()->subWeeks(2)->toIso8601String(); // 最近兩週

            foreach( $videos as $video )
            {
                $publishedAtIso = $video['snippet']['publishedAt']; // ISO 格式
                $publishedAt    = Carbon::parse($publishedAtIso)->toDateTimeString(); // 轉為 MySQL 格式

                if( $publishedAt >= $recentDate )
                {
                    $videoExists = YoutubeVideo::where('video_id', $video['id'])->exists();

                    if( !$videoExists )
                    {
                        YoutubeVideo::create([
                            'video_id'     => $video['id'],
                            'title'        => $video['snippet']['title'],
                            'channel_name' => $video['snippet']['channelTitle'],
                            'published_at' => $publishedAt, // 正確格式
                        ]);
                        $this->doDownloadProcess($video['id']); // 下載影片並上傳到telegram
                        $this->info('影片已儲存: ' . $video['snippet']['title']);
                    }
                    else
                    {
                        $this->info('影片已存在: ' . $video['snippet']['title']);
                    }
                }
            }
        }
        else
        {
            $this->error('Failed to fetch data. Error: ' . $response->status());
        }
    }

    public function doDownloadProcess($videoId)
    {
        $baseUrl = 'https://www.youtube.com/watch?v=';
        $url     = $baseUrl . $videoId;
        Artisan::call('youtube:download', ['url' => $url]);
    }
}
