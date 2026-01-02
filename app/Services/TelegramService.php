<?php

namespace App\Services;

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Models\TelegramBotLog;
use Longman\TelegramBot\Entities\InlineKeyboard;

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

        if (TelegramBotLog::where('update_id', $update['update_id'])->exists()) {
            Log::info('Update ID already exists, skipping...');
            return;
        }

        TelegramBotLog::create([
            'update_id' => $update['update_id'],
            'message' => json_encode($update),
        ]);
        Log::info('New update ID saved to database.');

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
                // 儲存原始文字以供後續判斷「音樂」或「影片」
                $originalText = $text;

                // 嘗試從文字中提取出 YouTube URL
                preg_match('/https?:\/\/[^\s]+/', $text, $matches);
                $url = $matches[0] ?? null;

                // 驗證網址是否為有效的 YouTube 網址
                // 驗證是否為有效 YouTube URL
                $chatId = '-4661576369';
                if (
                    !empty($url) &&
                    filter_var($url, FILTER_VALIDATE_URL) &&
                    preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\//', $url)
                ) {
                    if (strpos($originalText, '音樂') !== false) {
                        Artisan::call('youtube:download', [
                            'url' => $url,
                            '--audio-only' => true,
                        ]);
                    }
                    else if (strpos($originalText, '影片') !== false) {
                        Artisan::call('youtube:download', [
                            'url' => $url,
                        ]);
                    }
                }
                else {
                    Log::warning("無效的 YouTube 影片網址: $url");
                    $this->sendMessage($chatId, '請提供有效的 YouTube 影片網址');
                }
            }


            if (strpos($text, '重新啟動爬蟲') !== false)
            {
                $chatId = '-4664862172'; // 指定聊天室 ID

                // 執行 lsof -i :4444，檢查是否有佔用 4444 埠
                exec('lsof -i :4444', $output, $status);

                if ($status === 0)
                {
                    // 埠 4444 被佔用，終止該進程
                    Log::info( "埠 4444 被佔用，準備終止該進程...\n");
                    exec('kill -9 $(lsof -t -i :4444)', $killOutput, $killStatus);
                    if ($killStatus === 0)
                    {
                        $this->sendMessage($chatId, "✅ 成功終止埠 4444 的進程\n");
                        Log::info( "✅ 成功終止埠 4444 的進程\n");
                    }
                    else
                    {
                        $this->sendMessage($chatId, "❌ 無法終止埠 4444 的進程\n");
                        Log::info( "❌ 無法終止埠 4444 的進程\n");
                    }
                }
                else
                {
                    $this->sendMessage($chatId, "埠 4444 未被佔用\n");
                    Log::info( "埠 4444 未被佔用\n");
                }

                // 進入指定目錄並啟動 Selenium Server
                $this->sendMessage($chatId, "🚀 啟動 Selenium Server...\n");
                Log::info( "🚀 啟動 Selenium Server...\n");
                chdir('/var/www/Scraper-System/');
                exec('java -jar selenium-server.jar standalone --selenium-manager true > selenium.log 2>&1 &', $startOutput, $startStatus);

                if ($startStatus === 0)
                {
                    $this->sendMessage($chatId, "✅ Selenium Server 啟動成功\n");
                    Log::info( "✅ Selenium Server 啟動成功\n");
                }
                else
                {
                    $this->sendMessage($chatId, "❌ Selenium Server 啟動失敗，請檢查 selenium.log\n");
                    Log::info( "❌ Selenium Server 啟動失敗，請檢查 selenium.log\n");
                }
            }
        }

        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $data     = $callback['data'];   // yt|audio|VIDEO_ID
            $chatId   = $callback['message']['chat']['id'];

            Log::info('Callback received', compact('data'));

            [$type, $mode, $videoId, $encodedTitle] = array_pad(explode('|', $data), 4, null);

            $title = $encodedTitle
                ? base64_decode($encodedTitle)
                : '未知標題';

            if ($type === 'yt') {
                $url = "https://www.youtube.com/watch?v={$videoId}";

                if ($mode === 'audio') {
                    $this->sendMessage(
                        $chatId,
                        "🎵 開始下載音樂：\n{$title}\n請至輸出群查看檔案"
                    );

                    Artisan::call('youtube:download', [
                        'url' => $url,
                        '--audio-only' => true,
                    ]);
                }

                if ($mode === 'video') {
                    $this->sendMessage(
                        $chatId,
                        "🎬 開始下載影片：\n{$title}\n請至輸出群查看檔案"
                    );

                    Artisan::call('youtube:download', [
                        'url' => $url,
                    ]);
                }
            }

            // 一定要回覆 callback，不然 Telegram 會一直 loading
            Request::answerCallbackQuery([
                'callback_query_id' => $callback['id'],
                'text' => '已收到指令，開始處理',
                'show_alert' => false,
            ]);

            return;
        }

    }

    public function searchSong($keyword)
    {
        $apiKey = env('YOUTUBE_API_KEY');
        $url = 'https://www.googleapis.com/youtube/v3/search';

        $response = Http::get($url, [
            'part'            => 'snippet',
            'q'               => $keyword,
            'type'            => 'video',
            'videoCategoryId' => '10',
            'maxResults'      => 3,
            'key'             => $apiKey,
        ]);

        if (!$response->successful()) {
            Log::error('YouTube API 請求失敗', ['status' => $response->status()]);
            return;
        }

        $videos = $response->json()['items'];
        $chatId = '-4661576369';

        if (empty($videos)) {
            Request::sendMessage([
                'chat_id' => $chatId,
                'text'    => "🔍 找不到相關音樂\n\n關鍵字：{$keyword}\n請嘗試其他歌名或歌手 🙏",
            ]);
            return;
        }

        foreach ($videos as $video) {
            $videoId = $video['id']['videoId'];
            $title   = $video['snippet']['title'];
            $channel = $video['snippet']['channelTitle'];
            $encodedTitle = base64_encode(mb_substr($title, 0, 30)); // 防止過長
            $text =
                "🎵 {$title}\n" .
                "📺 {$channel}\n" .
                "🔗 https://www.youtube.com/watch?v={$videoId}";

            $keyboard = new InlineKeyboard(
                [
                    [
                        'text' => '🎵 下載音樂',
                        'callback_data' => "yt|audio|{$videoId}|{$encodedTitle}",
                    ],
                    [
                        'text' => '🎬 下載影片',
                        'callback_data' => "yt|video|{$videoId}|{$encodedTitle}",
                    ],
                ]
            );

            Request::sendMessage([
                'chat_id'      => $chatId,
                'text'         => $text,
                'reply_markup' => $keyboard,
            ]);
        }
    }
}
