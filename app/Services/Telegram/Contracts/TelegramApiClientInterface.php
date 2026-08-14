<?php

namespace App\Services\Telegram\Contracts;

use Longman\TelegramBot\Entities\InlineKeyboard;

interface TelegramApiClientInterface
{
    public function sendMessage(
        string $chatId,
        string $message,
        ?InlineKeyboard $keyboard = null
    );

    public function sendDocument(string $chatId, string $filePath, string $fileName);

    public function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): void;
}
