<?php

namespace App\Services;

use App\Models\GovOjt;
use App\Models\JudicialAuction;
use App\Models\TelegramBotLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class TelegramService
{
    protected $telegram;

    protected $apiKey;

    private const REPORT_CITIES = [
        '臺北市' => [
            'keywords' => ['臺北市'],
            'url' => 'https://prsweb.tcpd.gov.tw/#/New',
            'phone' => '0911510914',
        ],
        '新北市' => [
            'keywords' => ['新北市'],
            'url' => 'https://tvrs.ntpd.gov.tw/Home/Report_Add',
            'phone' => '0911510105',
        ],
        '台中市' => [
            'keywords' => ['台中市'],
            'url' => 'https://www.tchpd.npa.gov.tw/ch/mailbox/mailtraffic/mailtraffic?module=mailtraffic&id=7480#',
            'phone' => '0911510915',
        ],
        '台南市' => [
            'keywords' => ['台南市'],
            'url' => 'https://tr.tnpd.gov.tw/TrafficMailbox/Index/92929b01-cf5e-99d6-2539-bee668350a6d',
            'phone' => '0911510916',
        ],
        '高雄市' => [
            'keywords' => ['高雄市'],
            'url' => 'https://policemail.kcg.gov.tw/Statement.aspx',
            'phone' => '0911510917',
        ],
        '基隆市' => [
            'keywords' => ['基隆市'],
            'url' => 'https://tptv.klg.gov.tw/reportcase/index.aspx',
            'phone' => '0911510918',
        ],
    ];

    public function __construct()
    {
        $apiKey = config('telegram.apikey');
        $botUsername = config('telegram.username');

        if (! $apiKey || ! $botUsername) {
            throw new \Exception('Telegram bot configuration is missing.');
        }

        $this->telegram = new Telegram($apiKey, $botUsername);
    }

    public function sendMessage(string $chatId, string $message, ?array $keyboard = null)
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }

        try {
            $result = Request::sendMessage($data);

            return $result->isOk() ? $result->getDescription() : $result->printError();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function sendDocument(string $chatId, string $filePath, string $fileName)
    {
        try {
            $response = Http::timeout(600)
                ->attach(
                    'document',
                    file_get_contents($filePath),
                    $fileName
                )
                ->post('https://api.telegram.org/bot'.config('telegram.apikey').'/sendDocument', [
                    'chat_id' => $chatId,
                ]);

            if ($response->successful()) {
                return '檔案上傳成功';
            } else {
                return '檔案上傳失敗：'.$response->body();
            }
        } catch (\Exception $e) {
            return '檔案上傳失敗：'.$e->getMessage();
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
            'message' => json_encode($update),
        ]);

        Log::info('New update ID saved to database.');

        return false;
    }

    private function handleMessage(array $message): void
    {
        if (! isset($message['text'])) {
            return;
        }

        $text = trim($message['text']);
        $chatId = $message['chat']['id'];

        // 🔀 依聊天室分流
        switch ($chatId) {
            case -5276842274: // 方便檢舉專區
                $this->handleReportGroup($chatId, $text);
                break;

            case -5401712481: // 熱門音樂分享群
                $this->handleMusicGroup($chatId, $text);
                break;

            case -5434110194: // 法拍通知群
                $this->handleAuctionGroup($chatId, $text);
                break;

            case -5185362500: // 課程通知群
                $this->handleOjtGroup($chatId, $text);
                break;

                // case -4664862172: // 爬蟲管理群
                //     $this->handleCrawlerGroup($chatId, $text);
                //     break;

            default:
                Log::info('未定義聊天室訊息', compact('chatId', 'text'));
                break;
        }
    }

    private function handleOjtGroup(int $chatId, string $text): void
    {
        if (str_starts_with($text, 'ojt_summary:')) {

            $id = (int) str_replace(
                'ojt_summary:',
                '',
                $text
            );

            $course = GovOjt::find($id);

            if (! $course) {
                $this->sendMessage(
                    $chatId,
                    '❌ 找不到課程'
                );

                return;
            }

            $this->sendMessage(
                $chatId,
                "🧠 AI 課程分析\n\n"
                .($course->ai_summary ?? '尚未產生 AI 摘要')
            );

            return;
        }
    }

    private function handleAuctionGroup(int $chatId, string $text): void
    {
        // 輸入案件 ID
        $auctionId = intval($text);

        if (! $auctionId) {
            $this->sendMessage(
                $chatId,
                "請輸入法拍案件 ID，例如：\n\n184"
            );

            return;
        }

        $auction = JudicialAuction::find($auctionId);

        if (! $auction) {

            $this->sendMessage(
                $chatId,
                "❌ 找不到案件 ID：{$auctionId}"
            );

            return;
        }

        if (empty($auction->ai_summary)) {

            $this->sendMessage(
                $chatId,
                '⏳ 此案件尚未產生 AI 摘要，請先點擊案件通知中的按鈕。'
            );

            return;
        }

        $message =
            "🧠 AI摘要\n\n".
            "🏛 法院：{$auction->court}\n".
            "📌 案號：{$auction->case_no}\n\n".
            $auction->ai_summary;

        $this->sendMessage(
            $chatId,
            $message
        );
    }

    private function handleReportGroup(int|string $chatId, string $text): void
    {
        if (! $this->looksLikeLocation($text)) {
            return;
        }

        // 🔧 清洗地址（移除開頭郵遞區號）
        $cleanText = $this->normalizeAddressText($text);

        $cityData = $this->detectCity($cleanText);

        if (! $cityData) {
            $this->sendMessage($chatId, '⚠️ 無法判斷縣市，請確認地址是否包含「縣市名稱」');

            return;
        }

        $template = $this->buildReportTemplate($cityData['city'], $cleanText);

        // ① 檢舉文範本
        $this->sendMessage($chatId, $template);

        usleep(300000);

        // ② 檢舉網站
        $this->sendMessage(
            $chatId,
            "📍 判斷地點：{$cityData['city']}\n\n".
            "🔗 線上檢舉：\n{$cityData['url']}\n\n".
            '📞 簡訊報案電話：'
        );

        usleep(300000);

        // ③ 簡訊報案電話
        $this->sendMessage($chatId, $cityData['phone']);
    }

    private function handleMusicGroup(int $chatId, string $text): void
    {
        // 🎵 找歌
        if (str_contains($text, '找歌')) {
            $keyword = trim(str_replace('找歌', '', $text));

            if ($keyword !== '') {
                Log::info('搜尋歌曲', compact('keyword'));
                $this->searchSong($keyword);
            }

            return;
        }

        // ⬇️ 下載
        if (! str_contains($text, '下載')) {
            return;
        }

        preg_match('/https?:\/\/[^\s]+/', $text, $matches);
        $url = $matches[0] ?? null;

        if (! $this->isValidYoutubeUrl($url)) {
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
        return ! empty($url)
            && filter_var($url, FILTER_VALIDATE_URL)
            && preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\//', $url);
    }

    private function handleCrawlerGroup(int $chatId, string $text): void
    {
        if (! str_contains($text, '重新啟動爬蟲')) {
            return;
        }

        exec('lsof -i :4444', $output, $status);

        if ($status === 0) {
            exec('kill -9 $(lsof -t -i :4444)');
            $this->sendMessage($chatId, '✅ 已終止佔用 4444 埠的程序');
        } else {
            $this->sendMessage($chatId, 'ℹ️ 埠 4444 未被佔用');
        }

        chdir('/var/www/Scraper-System/');
        exec('java -jar selenium-server.jar standalone --selenium-manager true > selenium.log 2>&1 &');

        $this->sendMessage($chatId, '🚀 Selenium Server 已啟動');
    }

    private function formatTelegramText(string $text): string
    {
        // 移除 Markdown 標題符號 ###
        $text = preg_replace('/^#+\s*/m', '', $text);

        // **文字** → 文字
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);

        // 單獨 bullet * → •
        $text = preg_replace('/^\s*\*\s+/m', '• ', $text);

        // 避免多餘 ---
        $text = preg_replace('/^-{3,}$/m', '', $text);

        // 清除多餘空行
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private function handleOjtCallback(array $callback): void
    {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];

        $id = (int) str_replace(
            'ojt_summary:',
            '',
            $data
        );

        $course = GovOjt::find($id);

        if (! $course) {
            $this->sendMessage(
                $chatId,
                '❌ 找不到課程'
            );
        } else {
            $summary = $this->formatTelegramText(
                $course->ai_summary ?? '尚未產生 AI 摘要'
            );

            $this->sendMessage(
                $chatId,
                "🧠 AI 課程分析\n\n".$summary
            );
        }

        Request::answerCallbackQuery([
            'callback_query_id' => $callback['id'],
            'text' => '課程分析完成',
            'show_alert' => false,
        ]);
    }

    private function handleYoutubeCallback(array $callback): void
    {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];

        [$type, $mode, $videoId, $encodedTitle] =
            array_pad(explode('|', $data), 4, null);

        $title = $encodedTitle
            ? base64_decode($encodedTitle)
            : '未知標題';

        if ($type === 'yt') {

            $url = "https://www.youtube.com/watch?v={$videoId}";

            if ($mode === 'audio') {
                $this->sendMessage(
                    $chatId,
                    "🎵 開始下載音樂：\n{$title}"
                );

                Artisan::call('youtube:download', [
                    'url' => $url,
                    '--audio-only' => true,
                ]);
            }

            if ($mode === 'video') {
                $this->sendMessage(
                    $chatId,
                    "🎬 開始下載影片：\n{$title}"
                );

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

    private function handleAuctionCallback(array $callback): void
    {
        $data = $callback['data'];

        $chatId = $callback['message']['chat']['id'];

        $id = (int) str_replace(
            'auction_summary:',
            '',
            $data
        );

        $auction = JudicialAuction::find($id);

        if (! $auction) {

            $this->sendMessage(
                $chatId,
                '❌ 找不到法拍案件'
            );

        } else {

            $summary = $this->formatTelegramText(
                $auction->ai_summary ?? '尚未產生 AI 摘要'
            );

            $message =
                "🧠 法拍案件分析\n\n".
                "🏛 法院：{$auction->court}\n".
                "📌 案號：{$auction->case_no}\n\n".
                $summary;

            $this->sendMessage(
                $chatId,
                $message
            );
        }

        Request::answerCallbackQuery([
            'callback_query_id' => $callback['id'],
            'text' => '案件分析完成',
            'show_alert' => false,
        ]);
    }

    private function handleCallback(array $callback): void
    {
        $data = $callback['data'];

        if (str_starts_with($data, 'ojt_summary:')) {
            $this->handleOjtCallback($callback);

            return;
        }

        if (str_starts_with($data, 'yt|')) {
            $this->handleYoutubeCallback($callback);

            return;
        }

        if (str_starts_with($data, 'auction_summary:')) {
            $this->handleAuctionCallback($callback);

            return;
        }

        Request::answerCallbackQuery([
            'callback_query_id' => $callback['id'],
            'text' => '未知指令',
            'show_alert' => false,
        ]);
    }

    public function searchSong($keyword)
    {
        $apiKey = env('YOUTUBE_API_KEY');
        $url = 'https://www.googleapis.com/youtube/v3/search';

        $response = Http::get($url, [
            'part' => 'snippet',
            'q' => $keyword,
            'type' => 'video',
            //'videoCategoryId' => '10',
            'maxResults' => 3,
            'key' => $apiKey,
        ]);

        if (! $response->successful()) {
            Log::error('YouTube API 請求失敗', ['status' => $response->status()]);

            return;
        }

        $videos = $response->json()['items'];
        $chatId = '-5401712481';
        Log::info(json_encode(print_r($videos, true)));
        if (empty($videos)) {
            Request::sendMessage([
                'chat_id' => $chatId,
                'text' => "🔍 找不到相關音樂\n\n關鍵字：{$keyword}\n請嘗試其他歌名或歌手 🙏",
            ]);

            return;
        }

        foreach ($videos as $video) {
            $videoId = $video['id']['videoId'];
            $title = $video['snippet']['title'];
            $channel = $video['snippet']['channelTitle'];
            $encodedTitle = base64_encode(mb_substr($title, 0, 10, 'UTF-8'));  // 防止過長
            $text =
                "🎵 {$title}\n".
                "📺 {$channel}\n".
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
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => $keyboard,
            ]);
        }
    }

    private function looksLikeLocation(string $text): bool
    {
        // 含「市 / 區 / 路 / 巷 / 號」
        if (preg_match('/(市|區|路|街|巷|號)/u', $text)) {
            return true;
        }

        // 度分秒 or 小數經緯度
        if (preg_match('/\d{1,2}°\d{1,2}\'[\d.]+\"?[NS]/', $text)) {
            return true;
        }

        if (preg_match('/-?\d+\.\d+\s*,\s*-?\d+\.\d+/', $text)) {
            return true;
        }

        return false;
    }

    private function detectCity(string $text): ?array
    {
        foreach (self::REPORT_CITIES as $city => $data) {
            foreach ($data['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return [
                        'city' => $city,
                        'url' => $data['url'],
                        'phone' => $data['phone'],
                    ];
                }
            }
        }

        return null;
    }

    private function buildReportTemplate(string $city, string $locationText): string
    {
        return
            "違規地點：\n".
            "{$locationText}\n\n".
            "違規情形：\n".
            "車號 XXX-XXXX 違規停車，\n".
            "已嚴重影響行人通行及出入動線，\n".
            "造成用路人不便與安全疑慮。\n\n".
            '請派員前往查處並依法取締，謝謝。';
    }

    private function normalizeAddressText(string $text): string
    {
        // 移除開頭的郵遞區號，例如：22158新北市、 221 新北市
        return preg_replace('/^\s*\d{3,5}\s*/u', '', $text);
    }
}
