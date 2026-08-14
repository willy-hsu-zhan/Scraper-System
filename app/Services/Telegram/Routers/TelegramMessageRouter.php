<?php

namespace App\Services\Telegram\Routers;

use App\Services\Telegram\Handlers\AuctionHandler;
use App\Services\Telegram\Handlers\MusicHandler;
use App\Services\Telegram\Handlers\OjtHandler;
use App\Services\Telegram\Handlers\ReportHandler;
use App\Services\Telegram\TelegramChat;
use Illuminate\Support\Facades\Log;

class TelegramMessageRouter
{
    public function __construct(
        protected OjtHandler $ojtHandler,
        protected AuctionHandler $auctionHandler,
        protected MusicHandler $musicHandler,
        protected ReportHandler $reportHandler
    ) {
    }

    public function route(array $parsedUpdate): void
    {
        $chatId = $parsedUpdate['chat_id'] ?? null;
        $text = trim((string) ($parsedUpdate['message_text'] ?? ''));

        if ($chatId === null || $text === '') {
            return;
        }

        $chatIdInt = (int) $chatId;

        switch (true) {
            case $chatIdInt === TelegramChat::REPORT:
                $this->reportHandler->handleMessage($chatId, $text);
                return;
            case $chatIdInt === TelegramChat::MUSIC:
                $this->musicHandler->handleMessage($chatId, $text);
                return;
            case $chatIdInt === TelegramChat::AUCTION:
                $this->auctionHandler->handleMessage($chatId, $text);
                return;
            case $chatIdInt === TelegramChat::OJT:
                $this->ojtHandler->handleMessage($chatId, $text);
                return;
            default:
                Log::info('未定義聊天室訊息', ['chatId' => $chatId, 'text' => $text]);
                return;
        }
    }
}
