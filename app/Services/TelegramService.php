<?php

namespace App\Services;

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;

class TelegramService
{
    protected $telegram;

    public function __construct()
    {
        $apiKey = config('telegram.apikey');
        $botUsername = config('telegram.username');

        if (!$apiKey || !$botUsername) {
            throw new \Exception('Telegram bot configuration is missing.');
        }

        $this->telegram = new Telegram($apiKey, $botUsername);
    }

    public function sendMessage(string $chatId, string $message)
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        try {
            $result = Request::sendMessage($data);
            return $result->isOk() ? $result->getDescription() : $result->printError();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function sendDocument(string $chatId, string $documentPath)
    {
        $data = [
            'chat_id' => $chatId,
            'document' => new \CURLFile(realpath($documentPath)),
        ];

        try {
            $result = Request::sendDocument($data);
            return $result->isOk() ? $result->getDescription() : $result->printError();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
