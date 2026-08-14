<?php

namespace App\Services\Telegram\Routers;

use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\Telegram\Handlers\AuctionHandler;
use App\Services\Telegram\Handlers\OjtHandler;
use App\Services\Telegram\Handlers\YoutubeHandler;

class TelegramCallbackRouter
{
    public function __construct(
        protected TelegramApiClientInterface $apiClient,
        protected OjtHandler $ojtHandler,
        protected AuctionHandler $auctionHandler,
        protected YoutubeHandler $youtubeHandler
    ) {
    }

    public function route(array $parsedUpdate): void
    {
        $data = $parsedUpdate['callback_data'] ?? '';
        $callbackQueryId = $parsedUpdate['callback_query_id'] ?? null;
        $chatId = $parsedUpdate['chat_id'] ?? null;

        if ($callbackQueryId === null) {
            return;
        }

        if (str_starts_with($data, 'ojt_summary:')) {
            $this->ojtHandler->handleCallback($chatId, $data, $callbackQueryId);

            return;
        }

        if (str_starts_with($data, 'yt|')) {
            $this->youtubeHandler->handleCallback($chatId, $data, $callbackQueryId);

            return;
        }

        if (str_starts_with($data, 'auction_summary:')) {
            $this->auctionHandler->handleCallback($chatId, $data, $callbackQueryId);

            return;
        }

        $this->apiClient->answerCallbackQuery($callbackQueryId, '未知指令');
    }
}
