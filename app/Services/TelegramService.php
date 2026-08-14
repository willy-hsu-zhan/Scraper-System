<?php

namespace App\Services;

use App\Models\GovOjt;
use App\Models\JudicialAuction;
use App\Models\TelegramBotLog;
use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\Telegram\Contracts\TelegramMessageFormatterInterface;
use App\Services\Telegram\Contracts\TelegramUpdateParserInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Longman\TelegramBot\Entities\InlineKeyboard;

class TelegramService
{
    public const REPORT_GROUP_CHAT_ID = -5276842274;
    public const MUSIC_GROUP_CHAT_ID = -5401712481;
    public const YOUTUBE_MUSIC_SHARE_GROUP_CHAT_ID = self::MUSIC_GROUP_CHAT_ID;
    public const AUCTION_GROUP_CHAT_ID = -5434110194;
    public const OJT_GROUP_CHAT_ID = -5185362500;
    public const YOUTUBE_VIDEO_OUTPUT_LOG_GROUP_CHAT_ID = -5229387465;
    public const YOUTUBE_VIDEO_OUTPUT_GROUP_CHAT_ID = -5437101799;
    public const FREE_GAME_GROUP_CHAT_ID = -5107922211;
    public const BIRTHDAY_DISCOUNT_GROUP_CHAT_ID = -5445385394;
    public const CHICKPT_GROUP_CHAT_ID = -1004460159382;
    public const DCARD_GROUP_CHAT_ID = -4664862172;
    public const DELIVERY_COUPON_GROUP_CHAT_ID = -5419894094;
    public const RENT591_GROUP_CHAT_ID = -4675025353;
    public const PTT_GROUP_CHAT_ID = -5234655807;
    public const YOUTUBE_TRAVEL_GROUP_CHAT_ID = -5100480609;
    public const YOUTUBE_MUSIC_CATEGORY_GROUP_CHAT_ID = -4661576369;
    public const YOUTUBE_GAME_GROUP_CHAT_ID = -5023352255;
    public const YOUTUBE_MOVIE_GROUP_CHAT_ID = -4997962376;

    protected TelegramApiClientInterface $apiClient;
    protected TelegramMessageFormatterInterface $formatter;
    protected TelegramUpdateParserInterface $parser;

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

    public function __construct(
        TelegramApiClientInterface $apiClient,
        TelegramMessageFormatterInterface $formatter,
        TelegramUpdateParserInterface $parser
    )
    {
        $this->apiClient = $apiClient;
        $this->formatter = $formatter;
        $this->parser = $parser;
    }

    public function sendMessage(string $chatId, string $message, ?array $keyboard = null)
    {
        return $this->apiClient->sendMessage($chatId, $message, $keyboard);
    }

    public function sendDocument(string $chatId, string $filePath, string $fileName)
    {
        return $this->apiClient->sendDocument($chatId, $filePath, $fileName);
    }

    public function handleUpdate(array $update)
    {
        $parsedUpdate = $this->parser->parseUpdate($update);

        if (! isset($parsedUpdate['update_id']) || $this->isDuplicateUpdate($parsedUpdate['update_id'])) {
            return;
        }

        if ($parsedUpdate['type'] === 'message') {
            $this->handleMessage($parsedUpdate);
            return;
        }

        if ($parsedUpdate['type'] === 'callback_query') {
            $this->handleCallback($parsedUpdate);
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

    private function handleMessage(array $parsedUpdate): void
    {
        if (empty($parsedUpdate['message_text'])) {
            return;
        }

        $text = trim($parsedUpdate['message_text']);
        $chatId = $parsedUpdate['chat_id'];

        switch ($chatId) {
            case self::REPORT_GROUP_CHAT_ID:
                $this->handleReportGroup($chatId, $text);
                return;
            case self::MUSIC_GROUP_CHAT_ID:
                $this->handleMusicGroup($chatId, $text);
                return;
            case self::AUCTION_GROUP_CHAT_ID:
                $this->handleAuctionGroup($chatId, $text);
                return;
            case self::OJT_GROUP_CHAT_ID:
                $this->handleOjtGroup($chatId, $text);
                return;
            default:
                Log::info('未定義聊天室訊息', compact('chatId', 'text'));
                return;
        }
    }

    private function handleOjtGroup(int $chatId, string $text): void
    {
        if (! str_starts_with($text, 'ojt_summary:')) {
            return;
        }

        $id = (int) str_replace('ojt_summary:', '', $text);
        $course = GovOjt::find($id);

        if (! $course) {
            $this->sendMessage($chatId, '❌ 找不到課程');

            return;
        }

        $this->sendMessage(
            $chatId,
            '🧠 AI 課程分析\n\n'.($course->ai_summary ?? '尚未產生 AI 摘要')
        );
    }

    private function handleAuctionGroup(int $chatId, string $text): void
    {
        $auctionId = intval($text);

        if (! $auctionId) {
            $this->sendMessage($chatId, "請輸入法拍案件 ID，例如：\n\n184");

            return;
        }

        $auction = JudicialAuction::find($auctionId);

        if (! $auction) {
            $this->sendMessage($chatId, "❌ 找不到案件 ID：{$auctionId}");

            return;
        }

        if (empty($auction->ai_summary)) {
            $this->sendMessage($chatId, '⏳ 此案件尚未產生 AI 摘要，請先點擊案件通知中的按鈕。');

            return;
        }

        $message =
            "🧠 AI摘要\n\n".
            "🏛 法院：{$auction->court}\n".
            "📌 案號：{$auction->case_no}\n\n".
            $auction->ai_summary;

        $this->sendMessage($chatId, $message);
    }

    private function handleReportGroup(int|string $chatId, string $text): void
    {
        if (! $this->looksLikeLocation($text)) {
            return;
        }

        $cleanText = $this->normalizeAddressText($text);
        $cityData = $this->detectCity($cleanText);

        if (! $cityData) {
            $this->sendMessage($chatId, '⚠️ 無法判斷縣市，請確認地址是否包含「縣市名稱」');

            return;
        }

        $template = $this->buildReportTemplate($cityData['city'], $cleanText);

        $this->sendMessage($chatId, $template);
        usleep(300000);
        $this->sendMessage(
            $chatId,
            "📍 判斷地點：{$cityData['city']}\n\n"
            ."🔗 線上檢舉：\n{$cityData['url']}\n\n"
            .'📞 簡訊報案電話：'
        );
        usleep(300000);
        $this->sendMessage($chatId, $cityData['phone']);
    }

    private function handleMusicGroup(int $chatId, string $text): void
    {
        if (str_contains($text, '找歌')) {
            $keyword = trim(str_replace('找歌', '', $text));

            if ($keyword !== '') {
                Log::info('搜尋歌曲', compact('keyword'));
                $this->searchSong($keyword);
            }

            return;
        }

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
            $this->sendMessage($chatId, '🎵 開始下載音樂\n請至輸出群查看檔案');
            Artisan::call('youtube:download', [
                'url' => $url,
                '--audio-only' => true,
            ]);

            return;
        }

        if (str_contains($text, '影片')) {
            $this->sendMessage($chatId, '🎬 開始下載影片\n請至輸出群查看檔案');
            Artisan::call('youtube:download', [
                'url' => $url,
            ]);
        }
    }

    private function handleCallback(array $parsedUpdate): void
    {
        $data = $parsedUpdate['callback_data'] ?? '';
        $callbackQueryId = $parsedUpdate['callback_query_id'] ?? null;
        $chatId = $parsedUpdate['chat_id'];

        if ($callbackQueryId === null) {
            return;
        }

        if (str_starts_with($data, 'ojt_summary:')) {
            $this->handleOjtCallback($chatId, $data, $callbackQueryId);
            return;
        }

        if (str_starts_with($data, 'yt|')) {
            $this->handleYoutubeCallback($chatId, $data, $callbackQueryId);
            return;
        }

        if (str_starts_with($data, 'auction_summary:')) {
            $this->handleAuctionCallback($chatId, $data, $callbackQueryId);
            return;
        }

        $this->answerCallbackQuery($callbackQueryId, '未知指令');
    }

    private function handleOjtCallback(?int $chatId, string $data, string $callbackQueryId): void
    {
        if ($chatId === null) {
            return;
        }

        $id = (int) str_replace('ojt_summary:', '', $data);
        $course = GovOjt::find($id);

        if (! $course) {
            $this->sendMessage($chatId, '❌ 找不到課程');
            $this->answerCallbackQuery($callbackQueryId, '找不到課程');

            return;
        }

        $summary = $this->formatTelegramText($course->ai_summary ?? '尚未產生 AI 摘要');

        $this->sendMessage($chatId, "🧠 AI 課程分析\n\n{$summary}");
        $this->answerCallbackQuery($callbackQueryId, '課程分析完成');
    }

    private function handleYoutubeCallback(?int $chatId, string $data, string $callbackQueryId): void
    {
        if ($chatId === null) {
            return;
        }
        [$type, $mode, $videoId, $encodedTitle] = array_pad(explode('|', $data), 4, null);
        $title = $encodedTitle ? base64_decode($encodedTitle) : '未知標題';

        if ($type !== 'yt') {
            $this->answerCallbackQuery($callbackQueryId, '未知指令');
            return;
        }

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

        $this->answerCallbackQuery($callback['id'], '已收到指令，開始處理');
    }

    private function handleAuctionCallback(?int $chatId, string $data, string $callbackQueryId): void
    {
        if ($chatId === null) {
            return;
        }
        $id = (int) str_replace('auction_summary:', '', $data);
        $auction = JudicialAuction::find($id);

        if (! $auction) {
            $this->sendMessage($chatId, '❌ 找不到法拍案件');
            $this->answerCallbackQuery($callbackQueryId, '找不到案件');

            return;
        }

        $summary = $this->formatTelegramText($auction->ai_summary ?? '尚未產生 AI 摘要');

        $message =
            "🧠 法拍案件分析\n\n".
            "🏛 法院：{$auction->court}\n".
            "📌 案號：{$auction->case_no}\n\n".
            $summary;

        $this->sendMessage($chatId, $message);
        $this->answerCallbackQuery($callback['id'], '案件分析完成');
    }

    public function searchSong(string $keyword)
    {
        $apiKey = env('YOUTUBE_API_KEY');
        $url = 'https://www.googleapis.com/youtube/v3/search';

        $response = Http::get($url, [
            'part' => 'snippet',
            'q' => $keyword,
            'type' => 'video',
            'maxResults' => 3,
            'key' => $apiKey,
        ]);

        if (! $response->successful()) {
            Log::error('YouTube API 請求失敗', ['status' => $response->status()]);

            return;
        }

        $videos = $response->json()['items'] ?? [];
        $chatId = self::MUSIC_GROUP_CHAT_ID;

        if (empty($videos)) {
            $this->sendMessage($chatId, "🔍 找不到相關音樂\n\n關鍵字：{$keyword}\n請嘗試其他歌名或歌手 🙏");

            return;
        }

        foreach ($videos as $video) {
            $videoId = $video['id']['videoId'];
            $title = $video['snippet']['title'];
            $channel = $video['snippet']['channelTitle'];
            $encodedTitle = base64_encode(mb_substr($title, 0, 10, 'UTF-8'));

            $keyboard = new InlineKeyboard([
                [
                    [
                        'text' => '🎵 下載音樂',
                        'callback_data' => "yt|audio|{$videoId}|{$encodedTitle}",
                    ],
                    [
                        'text' => '🎬 下載影片',
                        'callback_data' => "yt|video|{$videoId}|{$encodedTitle}",
                    ],
                ],
            ]);

            $message =
                "🎵 {$title}\n".
                "📺 {$channel}\n".
                "🔗 https://www.youtube.com/watch?v={$videoId}";

            $this->sendMessage($chatId, $message, $keyboard);
        }
    }

    private function isValidYoutubeUrl(?string $url): bool
    {
        return ! empty($url)
            && filter_var($url, FILTER_VALIDATE_URL)
            && preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\//', $url);
    }

    private function formatTelegramText(string $text): string
    {
        $text = preg_replace('/^#+\s*/m', '', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/^\s*\*\s+/m', '• ', $text);
        $text = preg_replace('/^-{3,}$/m', '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): void
    {
        $this->apiClient->answerCallbackQuery($callbackQueryId, $text, $showAlert);
    }

    private function looksLikeLocation(string $text): bool
    {
        if (preg_match('/(市|區|路|街|巷|號)/u', $text)) {
            return true;
        }

        if (preg_match('/\d{1,2}°\d{1,2}\'[\d.]+"?[NS]/', $text)) {
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
        return preg_replace('/^\s*\d{3,5}\s*/u', '', $text);
    }
}
