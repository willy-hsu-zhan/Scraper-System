<?php

namespace App\Services\Telegram;

use App\Services\Telegram\Contracts\TelegramUpdateParserInterface;

class TelegramUpdateParser implements TelegramUpdateParserInterface
{
    public function parseUpdate(array $payload): array
    {
        $callbackMessage = $payload['callback_query']['message'] ?? null;

        return [
            'update_id' => $payload['update_id'] ?? null,
            'type' => isset($payload['callback_query']) ? 'callback_query' : 'message',
            'chat_id' => $payload['message']['chat']['id'] ?? $callbackMessage['chat']['id'] ?? null,
            'user_id' => $payload['message']['from']['id'] ?? $payload['callback_query']['from']['id'] ?? null,
            'username' => $payload['message']['from']['username'] ?? $payload['callback_query']['from']['username'] ?? null,
            'message_text' => $payload['message']['text'] ?? $payload['message']['caption'] ?? null,
            'message_id' => $payload['message']['message_id'] ?? null,
            'callback_query_id' => $payload['callback_query']['id'] ?? null,
            'callback_data' => $payload['callback_query']['data'] ?? null,
            'inline_message_id' => $payload['callback_query']['inline_message_id'] ?? null,
            'has_attachment' => $this->payloadHasAttachment($payload),
            'raw' => $payload,
        ];
    }

    protected function payloadHasAttachment(array $payload): bool
    {
        return isset($payload['message']['document'])
            || isset($payload['message']['photo'])
            || isset($payload['message']['audio'])
            || isset($payload['message']['video'])
            || isset($payload['message']['voice']);
    }
}
