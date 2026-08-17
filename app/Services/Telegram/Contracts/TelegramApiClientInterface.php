<?php

namespace App\Services\Telegram\Contracts;

interface TelegramApiClientInterface
{
    public function sendMessage(string $chatId, string $message, ?array $keyboard = null);

    public function sendDocument(string $chatId, string $filePath, string $fileName);

    public function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): void;
}
