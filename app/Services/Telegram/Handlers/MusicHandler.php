<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\Telegram\TelegramChat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Longman\TelegramBot\Entities\InlineKeyboard;

class MusicHandler
{
    public function __construct(
        protected TelegramApiClientInterface $apiClient
    ) {
    }

    public function handleMessage(int|string $chatId, string $text): void
    {
        if (str_contains($text, '找歌')) {
            $keyword = trim(str_replace('找歌', '', $text));

            if ($keyword !== '') {
                Log::info('搜尋歌曲', compact('keyword'));
                $this->searchSong($keyword);
            }

            return;
        }

        if (! str_contains($text, '下載')) {
            return;
        }

        preg_match('/https?:\/\/[^\s]+/', $text, $matches);
        $url = $matches[0] ?? null;

        if (! $this->isValidYoutubeUrl($url)) {
            $this->sendMessage($chatId, '請提供有效的 YouTube 影片網址');

            return;
        }

        if (str_contains($text, '音樂')) {
            $this->sendMessage($chatId, '🎵 開始下載音樂\n請至輸出群查看檔案');
            Artisan::call('youtube:download', [
                'url' => $url,
                '--audio-only' => true,
            ]);

            return;
        }

        if (str_contains($text, '影片')) {
            $this->sendMessage($chatId, '🎬 開始下載影片\n請至輸出群查看檔案');
            Artisan::call('youtube:download', [
                'url' => $url,
            ]);
        }
    }

    public function searchSong(string $keyword): void
    {
        $apiKey = env('YOUTUBE_API_KEY');
        $url = 'https://www.googleapis.com/youtube/v3/search';

        $response = Http::get($url, [
            'part' => 'snippet',
            'q' => $keyword,
            'type' => 'video',
            'maxResults' => 3,
            'key' => $apiKey,
        ]);

        if (! $response->successful()) {
            Log::error('YouTube API 請求失敗', ['status' => $response->status()]);

            return;
        }

        $videos = $response->json()['items'] ?? [];
        $chatId = TelegramChat::MUSIC;

        if (empty($videos)) {
            $this->sendMessage($chatId, "🔍 找不到相關音樂\n\n關鍵字：{$keyword}\n請嘗試其他歌名或歌手 🙏");

            return;
        }

        foreach ($videos as $video) {
            $videoId = $video['id']['videoId'];
            $title = $video['snippet']['title'];
            $channel = $video['snippet']['channelTitle'];
            $encodedTitle = base64_encode(mb_substr($title, 0, 10, 'UTF-8'));

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🎵 下載音樂',
                            'callback_data' => "yt|audio|{$videoId}|{$encodedTitle}",
                        ],
                        [
                            'text' => '🎬 下載影片',
                            'callback_data' => "yt|video|{$videoId}|{$encodedTitle}",
                        ],
                    ],
                ],
            ];

            $message = "🎵 {$title}\n".
                "📺 {$channel}\n".
                "🔗 https://www.youtube.com/watch?v={$videoId}";

            $this->sendMessage($chatId, $message, $keyboard);
        }
    }

    protected function sendMessage(int|string $chatId, string $message, ?array $keyboard = null): void
    {
        $this->apiClient->sendMessage((string) $chatId, $message, $keyboard);
    }

    protected function isValidYoutubeUrl(?string $url): bool
    {
        return ! empty($url)
            && filter_var($url, FILTER_VALIDATE_URL)
            && preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\//', $url);
    }
}
