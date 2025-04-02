<?php

namespace App\Services;

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $telegram;
    protected $apiKey;

    public function __construct()
    {
        $apiKey = config('telegram.apikey');
        $botUsername = config('telegram.username');

        if (!$apiKey || !$botUsername)
        {
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

        try
        {
            $result = Request::sendMessage($data);
            return $result->isOk() ? $result->getDescription() : $result->printError();
        }
        catch (\Exception $e)
        {
            return $e->getMessage();
        }
    }

    public function sendDocument(string $chatId, string $filePath, string $fileName)
    {
        try
        {
            $response = Http::timeout(600)
                ->attach(
                    'document',
                    file_get_contents($filePath),
                    $fileName
                )
                ->post('https://api.telegram.org/bot' . config('telegram.apikey') . '/sendDocument', [
                    'chat_id' => $chatId,
                ]);

            if ($response->successful())
            {
                return "檔案上傳成功";
            }
            else
            {
                return "檔案上傳失敗：" . $response->body();
            }
        }
        catch (\Exception $e)
        {
            return "檔案上傳失敗：" . $e->getMessage();
        }
    }

    public function handleUpdate($update)
    {
        Log::info('Handling update:', $update);  // 記錄處理的訊息

        // 假設你正在檢查是否有文字訊息
        if (isset($update['message']['text'])) {
            $text = $update['message']['text'];
            // 根據訊息執行動作，例如搜尋 YouTube 或其他處理
            if (strpos($text, '找歌') !== false) {
                // 執行搜尋動作
                Log::info("偵測到TELEGRAM 找歌");
                //$this->searchSong($text);
            }
        }
    }

}
