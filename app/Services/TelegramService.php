<?php

namespace App\Services;

use App\Models\TelegramBotLog;
use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\Telegram\Contracts\TelegramUpdateParserInterface;
use App\Services\Telegram\Routers\TelegramCallbackRouter;
use App\Services\Telegram\Routers\TelegramMessageRouter;
use Illuminate\Support\Facades\Log;

class TelegramService
{
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

    public function sendMessage(string $chatId, string $message, ?array $keyboard = null)
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
