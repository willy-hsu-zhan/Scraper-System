<?php

namespace App\Services;

use App\Models\TelegramBotLog;
use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\Telegram\Contracts\TelegramUpdateParserInterface;
use App\Services\Telegram\Routers\TelegramCallbackRouter;
use App\Services\Telegram\Routers\TelegramMessageRouter;
use App\Services\Telegram\TelegramChat;
use Illuminate\Support\Facades\Log;
use Longman\TelegramBot\Entities\InlineKeyboard;

class TelegramService
{
    public const REPORT_GROUP_CHAT_ID = TelegramChat::REPORT;
    public const MUSIC_GROUP_CHAT_ID = TelegramChat::MUSIC;
    public const YOUTUBE_MUSIC_SHARE_GROUP_CHAT_ID = TelegramChat::MUSIC;
    public const AUCTION_GROUP_CHAT_ID = TelegramChat::AUCTION;
    public const OJT_GROUP_CHAT_ID = TelegramChat::OJT;
    public const YOUTUBE_VIDEO_OUTPUT_LOG_GROUP_CHAT_ID = TelegramChat::YOUTUBE_VIDEO_OUTPUT_LOG;
    public const YOUTUBE_VIDEO_OUTPUT_GROUP_CHAT_ID = TelegramChat::YOUTUBE_VIDEO_OUTPUT;
    public const FREE_GAME_GROUP_CHAT_ID = TelegramChat::FREE_GAME;
    public const BIRTHDAY_DISCOUNT_GROUP_CHAT_ID = TelegramChat::BIRTHDAY_DISCOUNT;
    public const CHICKPT_GROUP_CHAT_ID = TelegramChat::CHICKPT;
    public const DCARD_GROUP_CHAT_ID = TelegramChat::DCARD;
    public const DELIVERY_COUPON_GROUP_CHAT_ID = TelegramChat::DELIVERY_COUPON;
    public const RENT591_GROUP_CHAT_ID = TelegramChat::RENT591;
    public const PTT_GROUP_CHAT_ID = TelegramChat::PTT;
    public const YOUTUBE_TRAVEL_GROUP_CHAT_ID = TelegramChat::YOUTUBE_TRAVEL;
    public const YOUTUBE_MUSIC_CATEGORY_GROUP_CHAT_ID = TelegramChat::YOUTUBE_MUSIC_CATEGORY;
    public const YOUTUBE_GAME_GROUP_CHAT_ID = TelegramChat::YOUTUBE_GAME;
    public const YOUTUBE_MOVIE_GROUP_CHAT_ID = TelegramChat::YOUTUBE_MOVIE;

    protected TelegramApiClientInterface $apiClient;
    protected TelegramUpdateParserInterface $parser;
    protected TelegramMessageRouter $messageRouter;
    protected TelegramCallbackRouter $callbackRouter;

    public function __construct(
        TelegramApiClientInterface $apiClient,
        TelegramUpdateParserInterface $parser,
        TelegramMessageRouter $messageRouter,
        TelegramCallbackRouter $callbackRouter
    ) {
        $this->apiClient = $apiClient;
        $this->parser = $parser;
        $this->messageRouter = $messageRouter;
        $this->callbackRouter = $callbackRouter;
    }

    public function sendMessage(
        string $chatId,
        string $message,
        ?InlineKeyboard $keyboard = null
    )
    {
        return $this->apiClient->sendMessage($chatId, $message, $keyboard);
    }

    public function sendDocument(string $chatId, string $filePath, string $fileName)
    {
        return $this->apiClient->sendDocument($chatId, $filePath, $fileName);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): void
    {
        $this->apiClient->answerCallbackQuery($callbackQueryId, $text, $showAlert);
    }

    public function handleUpdate(array $update)
    {
        $parsedUpdate = $this->parser->parseUpdate($update);

        if (! isset($parsedUpdate['update_id']) || $this->isDuplicateUpdate($parsedUpdate['update_id'])) {
            return;
        }

        if ($parsedUpdate['type'] === 'message') {
            $this->messageRouter->route($parsedUpdate);
            return;
        }

        if ($parsedUpdate['type'] === 'callback_query') {
            $this->callbackRouter->route($parsedUpdate);
        }
    }

    protected function isDuplicateUpdate(array $update): bool
    {
        if (TelegramBotLog::where('update_id', $update['update_id'])->exists()) {
            Log::info('Update ID already exists, skipping...');

            return true;
        }

        TelegramBotLog::create([
            'update_id' => $update['update_id'],
            'message' => json_encode($update),
        ]);

        Log::info('New update ID saved to database.');

        return false;
    }
}
