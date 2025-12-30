<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\YoutubeVideo;
use App\Services\TelegramService;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Request;

class SearchYoutubeMusic extends Command
{
    protected $signature = 'app:search-youtube-music';
    protected $description = 'Fetch popular YouTube music and push to Telegram with download buttons';

    protected TelegramService $telegramService;
    protected string $chatId = '-4661576369';

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    public function handle()
    {
        $apiKey = env('YOUTUBE_API_KEY');

        $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part'            => 'snippet',
            'chart'           => 'mostPopular',
            'regionCode'      => 'TW',
            'videoCategoryId' => '10',
            'maxResults'      => 4,
            'key'             => $apiKey,
        ]);

        if (!$response->successful()) {
            Log::error('YouTube API failed', ['status' => $response->status()]);
            return 1;
        }

        $recentLimit = Carbon::now()->subWeeks(2);

        foreach ($response->json('items') as $video) {

            $publishedAt = Carbon::parse($video['snippet']['publishedAt']);
            if ($publishedAt->lt($recentLimit)) {
                continue;
            }

            $videoId = $video['id'];

            // 已推播過就跳過
            if (YoutubeVideo::where('video_id', $videoId)->exists()) {
                continue;
            }

            YoutubeVideo::create([
                'video_id'     => $videoId,
                'title'        => $video['snippet']['title'],
                'channel_name' => $video['snippet']['channelTitle'],
                'published_at' => $publishedAt->toDateTimeString(),
            ]);

            $text =
                "🔥 熱門音樂\n\n" .
                "🎵 {$video['snippet']['title']}\n" .
                "📺 {$video['snippet']['channelTitle']}\n" .
                "🔗 https://www.youtube.com/watch?v={$videoId}";

            // 👇 關鍵：下載按鈕
            $keyboard = new InlineKeyboard(
                [
                    [
                        'text' => '🎵 下載音樂',
                        'callback_data' => "yt|audio|{$videoId}",
                    ],
                    [
                        'text' => '🎬 下載影片',
                        'callback_data' => "yt|video|{$videoId}",
                    ],
                ]
            );

            Request::sendMessage([
                'chat_id'      => $this->chatId,
                'text'         => $text,
                'reply_markup' => $keyboard,
            ]);

            sleep(3);
        }

        return 0;
    }
}
