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

class SearchYoutube extends Command
{
    protected $signature = 'app:search-youtube {region?} {category?}';
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

        $categories = [
            '19' => [
                'label'   => '✈️ 旅遊',
                'chat_id' => '-5100480609',
            ],
            '10' => [
                'label'   => '🎵 音樂',
                'chat_id' => '-4661576369',
            ],
            '20' => [
                'label'   => '🎮 遊戲',
                'chat_id' => '-5023352255',
            ],
            '24' => [
                'label'   => '🎬 電影',
                'chat_id' => '-4997962376',
            ],
        ];


        $regionInput   = strtoupper($this->argument('region') ?? 'all');
        $categoryInput = $this->argument('category') ?? 'all';

        $useCategories = ($categoryInput === 'all')
            ? $categories
            : array_intersect_key($categories, [$categoryInput => true]);

        if (empty($useCategories)) {
            $this->error("❌ 不支援的分類 ID：{$categoryInput}");
            return Command::FAILURE;
        }

        $allRegions = [
            'TW' => '🇹🇼 中文熱門',
            'US' => '🇺🇸 英文熱門',
            'JP' => '🇯🇵 日文熱門',
            'KR' => '🇰🇷 韓文熱門',
        ];

        $useRegions = ($regionInput === 'all')
            ? $allRegions
            : array_intersect_key($allRegions, [$regionInput => true]);

        if (empty($useRegions)) {
            $this->error("❌ 不支援的地區代碼：{$regionInput}");
            return Command::FAILURE;
        }


        $recentLimit = Carbon::now()->subWeeks(2);

        foreach ($useRegions as $regionCode => $regionLabel) {
            foreach ($useCategories as $categoryId => $categoryConfig) {

                $this->info("▶ {$regionCode} / {$categoryConfig['label']}");

                $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                    'part'            => 'snippet',
                    'chart'           => 'mostPopular',
                    'regionCode'      => $regionCode,
                    'videoCategoryId' => $categoryId,
                    'maxResults'      => 4,
                    'key'             => $apiKey,
                ]);

                if (!$response->successful()) {
                    Log::error('YouTube API failed', [
                        'region'   => $regionCode,
                        'category' => $categoryId,
                        'status'   => $response->status(),
                    ]);
                    continue;
                }

                foreach ($response->json('items', []) as $video) {

                    $publishedAt = Carbon::parse($video['snippet']['publishedAt']);
                    if ($publishedAt->lt($recentLimit)) {
                        continue;
                    }

                    $videoId = $video['id'];

                    if (YoutubeVideo::where('video_id', $videoId)->exists()) {
                        continue;
                    }

                    YoutubeVideo::create([
                        'video_id'     => $videoId,
                        'title'        => $video['snippet']['title'],
                        'channel_name' => $video['snippet']['channelTitle'],
                        'published_at' => $publishedAt->toDateTimeString(),
                        'region'       => $regionCode,
                        'category_id'  => $categoryId,
                    ]);

                    $text =
                        "{$regionLabel} {$categoryConfig['label']}\n\n" .
                        "🎬 {$video['snippet']['title']}\n" .
                        "📺 {$video['snippet']['channelTitle']}\n" .
                        "🔗 https://www.youtube.com/watch?v={$videoId}";

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
                        'chat_id'      => $categoryConfig['chat_id'],
                        'text'         => $text,
                        'reply_markup' => $keyboard,
                    ]);

                    sleep(3);
                }
            }
        }

        return 0;
    }

}
