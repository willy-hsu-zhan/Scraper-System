<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use Illuminate\Support\Facades\Artisan;

class YoutubeHandler
{
    public function __construct(
        protected TelegramApiClientInterface $apiClient
    ) {
    }

    public function handleCallback(?int $chatId, string $data, string $callbackQueryId): void
    {
        if ($chatId === null) {
            return;
        }

        [$type, $mode, $videoId, $encodedTitle] = array_pad(explode('|', $data), 4, null);
        $title = $encodedTitle ? base64_decode($encodedTitle) : '未知標題';

        if ($type !== 'yt') {
            $this->apiClient->answerCallbackQuery($callbackQueryId, '未知指令');
            return;
        }

        $url = "https://www.youtube.com/watch?v={$videoId}";

        if ($mode === 'audio') {
            $this->sendMessage($chatId, "🎵 開始下載音樂：\n{$title}");
            Artisan::call('youtube:download', [
                'url' => $url,
                '--audio-only' => true,
            ]);
        }

        if ($mode === 'video') {
            $this->sendMessage($chatId, "🎬 開始下載影片：\n{$title}");
            Artisan::call('youtube:download', [
                'url' => $url,
            ]);
        }

        $this->apiClient->answerCallbackQuery($callbackQueryId, '已收到指令，開始處理');
    }

    protected function sendMessage(int|string $chatId, string $message): void
    {
        $this->apiClient->sendMessage((string) $chatId, $message);
    }
}
