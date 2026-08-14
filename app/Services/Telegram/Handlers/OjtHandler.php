<?php

namespace App\Services\Telegram\Handlers;

use App\Models\GovOjt;
use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\Telegram\Contracts\TelegramMessageFormatterInterface;

class OjtHandler
{
    public function __construct(
        protected TelegramApiClientInterface $apiClient,
        protected TelegramMessageFormatterInterface $formatter
    ) {
    }

    public function handleMessage(int|string $chatId, string $text): void
    {
        if (! str_starts_with($text, 'ojt_summary:')) {
            return;
        }

        $id = (int) str_replace('ojt_summary:', '', $text);
        $course = GovOjt::find($id);

        if (! $course) {
            $this->sendMessage($chatId, '❌ 找不到課程');

            return;
        }

        $this->sendMessage(
            $chatId,
            '🧠 AI 課程分析\n\n'.($course->ai_summary ?? '尚未產生 AI 摘要')
        );
    }

    public function handleCallback(?int $chatId, string $data, string $callbackQueryId): void
    {
        if ($chatId === null) {
            return;
        }

        $id = (int) str_replace('ojt_summary:', '', $data);
        $course = GovOjt::find($id);

        if (! $course) {
            $this->sendMessage($chatId, '❌ 找不到課程');
            $this->apiClient->answerCallbackQuery($callbackQueryId, '找不到課程');

            return;
        }

        $summary = $this->formatter->formatTelegramText($course->ai_summary ?? '尚未產生 AI 摘要');

        $this->sendMessage($chatId, "🧠 AI 課程分析\n\n{$summary}");
        $this->apiClient->answerCallbackQuery($callbackQueryId, '課程分析完成');
    }

    protected function sendMessage(int|string $chatId, string $message): void
    {
        $this->apiClient->sendMessage((string) $chatId, $message);
    }
}
