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
        Log::info('Handling update:', $update);

        // === Update 去重 ===
        if ($this->isDuplicateUpdate($update)) {
            return;
        }

        // === 一般訊息 ===
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }

        // === Callback ===
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
            return;
        }
    }

    private function isDuplicateUpdate(array $update): bool
    {
        if (TelegramBotLog::where('update_id', $update['update_id'])->exists()) {
            Log::info('Update ID already exists, skipping...');
            return true;
        }

        TelegramBotLog::create([
            'update_id' => $update['update_id'],
            'message'   => json_encode($update),
        ]);

        Log::info('New update ID saved to database.');
        return false;
    }

    private function handleMessage(array $message): void
    {
        if (!isset($message['text'])) {
            return;
        }

        $text   = trim($message['text']);
        $chatId = $message['chat']['id'];

        // 🔀 依聊天室分流
        switch ($chatId) {
            case -5276842274: // 方便檢舉專區
                $this->handleReportGroup($chatId, $text);
                break;

            case -4661576369: // 熱門音樂分享群
                $this->handleMusicGroup($chatId, $text);
                break;

            case -4664862172: // 爬蟲管理群
                $this->handleCrawlerGroup($chatId, $text);
                break;

            default:
                Log::info("未定義聊天室訊息", compact('chatId', 'text'));
                break;
        }
    }

    private function handleReportGroup(int $chatId, string $text): void
    {
        if (str_contains($text, '地址')) {
            $this->sendMessage(
                $chatId,
                "📍 請貼上違規地址，例如：\n台北市大安區信義路三段"
            );
        }
    }

    private function handleMusicGroup(int $chatId, string $text): void
    {
        // 🎵 找歌
        if (str_contains($text, '找歌')) {
            $keyword = trim(str_replace('找歌', '', $text));

            if ($keyword !== '') {
                Log::info("搜尋歌曲", compact('keyword'));
                $this->searchSong($keyword);
            }
            return;
        }

        // ⬇️ 下載
        if (!str_contains($text, '下載')) {
            return;
        }

        preg_match('/https?:\/\/[^\s]+/', $text, $matches);
        $url = $matches[0] ?? null;

        if (!$this->isValidYoutubeUrl($url)) {
            $this->sendMessage($chatId, '請提供有效的 YouTube 影片網址');
            return;
        }

        if (str_contains($text, '音樂')) {
            $this->sendMessage($chatId, "🎵 開始下載音樂\n請至輸出群查看檔案");
            Artisan::call('youtube:download', [
                'url' => $url,
                '--audio-only' => true,
            ]);
            return;
        }

        if (str_contains($text, '影片')) {
            $this->sendMessage($chatId, "🎬 開始下載影片\n請至輸出群查看檔案");
            Artisan::call('youtube:download', [
                'url' => $url,
            ]);
        }
    }

    private function isValidYoutubeUrl(?string $url): bool
    {
        return !empty($url)
            && filter_var($url, FILTER_VALIDATE_URL)
            && preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\//', $url);
    }

    private function handleCrawlerGroup(int $chatId, string $text): void
    {
        if (!str_contains($text, '重新啟動爬蟲')) {
            return;
        }

        exec('lsof -i :4444', $output, $status);

        if ($status === 0) {
            exec('kill -9 $(lsof -t -i :4444)');
            $this->sendMessage($chatId, "✅ 已終止佔用 4444 埠的程序");
        } else {
            $this->sendMessage($chatId, "ℹ️ 埠 4444 未被佔用");
        }

        chdir('/var/www/Scraper-System/');
        exec('java -jar selenium-server.jar standalone --selenium-manager true > selenium.log 2>&1 &');

        $this->sendMessage($chatId, "🚀 Selenium Server 已啟動");
    }

    private function handleCallback(array $callback): void
    {
        $data   = $callback['data'];
        $chatId = $callback['message']['chat']['id'];

        [$type, $mode, $videoId, $encodedTitle] = array_pad(explode('|', $data), 4, null);
        $title = $encodedTitle ? base64_decode($encodedTitle) : '未知標題';

        if ($type === 'yt') {
            $url = "https://www.youtube.com/watch?v={$videoId}";

            if ($mode === 'audio') {
                $this->sendMessage($chatId, "🎵 開始下載音樂：\n{$title}");
                Artisan::call('youtube:download', [
                    'url' => $url,
                    '--audio-only' => true,
                ]);
            }

            if ($mode === 'video') {
                $this->sendMessage($chatId, "🎬 開始下載影片：\n{$title}");
                Artisan::call('youtube:download', [
                    'url' => $url,
                ]);
            }
        }

        Request::answerCallbackQuery([
            'callback_query_id' => $callback['id'],
            'text' => '已收到指令，開始處理',
            'show_alert' => false,
        ]);
    }


    public function searchSong($keyword)
    {
        $apiKey = env('YOUTUBE_API_KEY');
        $url = 'https://www.googleapis.com/youtube/v3/search';

        $response = Http::get($url, [
            'part'            => 'snippet',
            'q'               => $keyword,
            'type'            => 'video',
            //'videoCategoryId' => '10',
            'maxResults'      => 3,
            'key'             => $apiKey,
        ]);

        if (!$response->successful()) {
            Log::error('YouTube API 請求失敗', ['status' => $response->status()]);
            return;
        }

        $videos = $response->json()['items'];
        $chatId = '-4661576369';
        Log::info(json_encode(print_r($videos,true)));
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
            $encodedTitle = base64_encode(mb_substr($title, 0, 10, 'UTF-8'));  // 防止過長
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
