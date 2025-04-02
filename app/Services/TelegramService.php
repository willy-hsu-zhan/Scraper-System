<?php

namespace App\Services;

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

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
        if (isset($update['message']['text']))
        {
            $text = $update['message']['text'];

            // 找歌case
            if (strpos($text, '找歌') !== false)
            {
                // 刪除 "找歌" 這兩個字
                $text = str_replace('找歌', '', $text);

                // 如果訊息不為空並且包含 "找歌"，則執行搜尋
                if (!empty($text))
                {
                    // 執行搜尋動作
                    $text = trim($text);
                    Log::info($text);
                    $this->searchSong($text);  // 可以在這裡執行搜尋動作
                }
                else
                {
                    Log::info('搜尋關鍵字為空');
                }
            }

            // 下載 case
            if (strpos($text, '下載') !== false)
            {
                $text = str_replace('下載', '', $text);
                $text = trim($text); // 確保去掉 "下載" 之後不留空格

                // 確保字串是有效的 URL 並且來自 YouTube
                if (!empty($text) && filter_var($text, FILTER_VALIDATE_URL) && preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)[\w-]+$/', $text))
                {
                    $chatId = '-4661576369'; // 指定聊天室 ID
                    $message = '開始download影片 ...';
                    $this->sendMessage($chatId, $message);
                    Log::info("開始download影片: $text");
                    Artisan::call('youtube:download', ['url' => $text]);
                }
                else
                {
                    Log::warning("無效的 YouTube 影片網址: $text");
                }
            }
        }
    }

    public function searchSong($keyword)
    {
        $apiKey = env('YOUTUBE_API_KEY');
        $searchQuery = $keyword; // 設定你的搜尋關鍵字
        $url = 'https://www.googleapis.com/youtube/v3/search';

        // 發送 GET 請求，加入搜尋關鍵字參數
        $response = Http::get($url, [
            'part'            => 'snippet',
            'q'               => $searchQuery,  // 搜尋關鍵字
            'type'            => 'video',       // 只搜尋影片
            'videoCategoryId' => '10',          // 音樂類別
            'maxResults'      => 4,             // 限制回傳只有4筆
            'key'             => $apiKey,
        ]);

        if ($response->successful())
        {
            $videos = $response->json()['items'];
            $chatId = '-4661576369'; // 指定聊天室 ID

            $message = "🔍 搜尋關鍵字: {$keyword}\n\n";
            foreach ($videos as $video) {
                $videoId     = $video['id']['videoId'];
                $title       = $video['snippet']['title'];
                $channelName = $video['snippet']['channelTitle'];
                $videoUrl    = "https://www.youtube.com/watch?v={$videoId}";

                $message .= "🎵 *{$title}*  \n📺 頻道: {$channelName}\n🔗 [觀看影片]({$videoUrl})\n\n";
            }

            // 透過 sendMessage 方法發送訊息到聊天室
            $this->sendMessage($chatId, $message);
            $this->sendMessage($chatId, "請輸入 download(中文)'URL' 來download(中文)影片");
        }
        else
        {
            Log::error('YouTube API 請求失敗，錯誤碼: ' . $response->status());
        }
    }

    // public function doDownloadProcess($videoId)
    // {
    //     $baseUrl = 'https://www.youtube.com/watch?v=';
    //     $url     = $baseUrl . $videoId;
    //     Artisan::call('youtube:download', ['url' => $url]);
    // }
}
