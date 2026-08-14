<?php

namespace App\Services\Telegram\Handlers;

use App\Models\JudicialAuction;
use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\Telegram\Contracts\TelegramMessageFormatterInterface;

class AuctionHandler
{
    public function __construct(
        protected TelegramApiClientInterface $apiClient,
        protected TelegramMessageFormatterInterface $formatter
    ) {
    }

    public function handleMessage(int|string $chatId, string $text): void
    {
        $auctionId = intval($text);

        if (! $auctionId) {
            $this->sendMessage($chatId, "請輸入法拍案件 ID，例如：\n\n184");
            return;
        }

        $auction = JudicialAuction::find($auctionId);

        if (! $auction) {
            $this->sendMessage($chatId, "❌ 找不到案件 ID：{$auctionId}");
            return;
        }

        if (empty($auction->ai_summary)) {
            $this->sendMessage($chatId, '⏳ 此案件尚未產生 AI 摘要，請先點擊案件通知中的按鈕。');
            return;
        }

        $message = "🧠 AI摘要\n\n".
            "🏛 法院：{$auction->court}\n".
            "📌 案號：{$auction->case_no}\n\n".
            $auction->ai_summary;

        $this->sendMessage($chatId, $message);
    }

    public function handleCallback(?int $chatId, string $data, string $callbackQueryId): void
    {
        if ($chatId === null) {
            return;
        }

        $id = (int) str_replace('auction_summary:', '', $data);
        $auction = JudicialAuction::find($id);

        if (! $auction) {
            $this->sendMessage($chatId, '❌ 找不到法拍案件');
            $this->apiClient->answerCallbackQuery($callbackQueryId, '找不到案件');
            return;
        }

        $summary = $this->formatter->formatTelegramText($auction->ai_summary ?? '尚未產生 AI 摘要');
        $message = "🧠 法拍案件分析\n\n".
            "🏛 法院：{$auction->court}\n".
            "📌 案號：{$auction->case_no}\n\n".
            $summary;

        $this->sendMessage($chatId, $message);
        $this->apiClient->answerCallbackQuery($callbackQueryId, '案件分析完成');
    }

    protected function sendMessage(int|string $chatId, string $message): void
    {
        $this->apiClient->sendMessage((string) $chatId, $message);
    }
}
