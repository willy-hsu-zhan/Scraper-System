<?php

namespace App\Services\Telegram;

use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use Illuminate\Support\Facades\Http;
use Longman\TelegramBot\Request;

class TelegramApiClient implements TelegramApiClientInterface
{
    protected string $apiKey;
    protected string $apiBaseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('telegram.apikey', '');
        $this->apiBaseUrl = $this->apiKey !== ''
            ? "https://api.telegram.org/bot{$this->apiKey}"
            : '';
    }

    public function sendMessage(string $chatId, string $message, ?array $keyboard = null)
    {
        if ($this->apiKey === '') {
            return 'Telegram bot API key is not configured.';
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        }

        return $this->sendRequest($payload);
    }

    public function sendDocument(string $chatId, string $filePath, string $fileName)
    {
        if ($this->apiKey === '') {
            return 'Telegram bot API key is not configured.';
        }

        try {
            $response = Http::timeout(600)
                ->attach('document', file_get_contents($filePath), $fileName)
                ->post($this->getApiUrl('sendDocument'), [
                    'chat_id' => $chatId,
                ]);

            return $response->successful()
                ? '檔案上傳成功'
                : '檔案上傳失敗：'.$response->body();
        } catch (\Exception $e) {
            return '檔案上傳失敗：'.$e->getMessage();
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): void
    {
        if ($this->apiKey === '') {
            return;
        }

        Request::answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    protected function sendRequest(array $payload)
    {
        try {
            $result = Request::sendMessage($payload);

            return $result->isOk() ? $result->getDescription() : $result->printError();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    protected function getApiUrl(string $path): string
    {
        return rtrim($this->apiBaseUrl, '/') . '/' . ltrim($path, '/');
    }
}
