<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\Contracts\TelegramApiClientInterface;

class ReportHandler
{
    private const REPORT_CITIES = [
        '臺北市' => [
            'keywords' => ['臺北市'],
            'url' => 'https://prsweb.tcpd.gov.tw/#/New',
            'phone' => '0911-510914',
        ],
        '新北市' => [
            'keywords' => ['新北市'],
            'url' => 'https://tvrs.ntpd.gov.tw/Home/Report_Add',
            'phone' => '0911-510105',
        ],
        '台中市' => [
            'keywords' => ['台中市'],
            'url' => 'https://www.tchpd.npa.gov.tw/ch/mailbox/mailtraffic/mailtraffic?module=mailtraffic&id=7480#',
            'phone' => '0911-510915',
        ],
        '台南市' => [
            'keywords' => ['台南市'],
            'url' => 'https://tr.tnpd.gov.tw/TrafficMailbox/Index/92929b01-cf5e-99d6-2539-bee668350a6d',
            'phone' => '0911-510916',
        ],
        '高雄市' => [
            'keywords' => ['高雄市'],
            'url' => 'https://policemail.kcg.gov.tw/Statement.aspx',
            'phone' => '0911-510917',
        ],
        '基隆市' => [
            'keywords' => ['基隆市'],
            'url' => 'https://tptv.klg.gov.tw/reportcase/index.aspx',
            'phone' => '0911-510918',
        ],
    ];

    public function __construct(
        protected TelegramApiClientInterface $apiClient
    ) {
    }

    public function handleMessage(int|string $chatId, string $text): void
    {
        // respond with intro keyboard for common triggers
        $lower = mb_strtolower(trim($text));
        if (in_array($lower, ['/start', '檢舉', '報案', 'help', '說明'])) {
            $this->sendReportIntro($chatId);
            return;
        }

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

        $this->sendMessage($chatId, $template, $this->getReportKeyboard());
        usleep(300000);
        $this->sendMessage(
            $chatId,
            "📍 判斷地點：{$cityData['city']}\n\n"
            ."🔗 線上檢舉：\n{$cityData['url']}\n\n"
            .'📞 簡訊報案電話：',
            $this->getReportKeyboard()
        );
        usleep(300000);
        $this->sendMessage($chatId, $cityData['phone'], $this->getReportKeyboard());
    }

    /**
     * Handle incoming location (latitude/longitude)
     */
    public function handleLocation(int|string $chatId, float $latitude, float $longitude): void
    {
        // Log receipt
        \Illuminate\Support\Facades\Log::info('Telegram location received', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        // Acknowledge to user
        $message = "📍 已取得目前位置\n\n緯度：{$latitude}\n經度：{$longitude}\n\n正在判斷所在地區...";
        $this->sendMessage($chatId, $message, $this->getReportKeyboard());

        // Use geocoding service to reverse geocode
        try {
            $geoService = new \App\Services\GeocodingService();
            $result = $geoService->reverse($latitude, $longitude);

            if (! $result || empty($result['address'])) {
                \Illuminate\Support\Facades\Log::warning('Unable to detect address from reverse geocoding', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

                $this->sendMessage($chatId, '⚠️ 無法判斷地址，請輸入地址文字或稍後再試。', $this->getReportKeyboard());
                return;
            }

            $address = $result['address'];
            \Illuminate\Support\Facades\Log::info('Reverse geocoding success', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $address,
            ]);

            // Normalize and detect city using existing logic
            $cleanText = $this->normalizeAddressText($address);
            $cityData = $this->detectCity($cleanText);

            if (! $cityData) {
                \Illuminate\Support\Facades\Log::warning('Unable to detect report city', [
                    'address' => $address,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

                $this->sendMessage($chatId, "⚠️ 找不到符合的縣市：\n{$address}\n請手動輸入地址或確認位置。", $this->getReportKeyboard());
                return;
            }

            $template = $this->buildReportTemplate($cityData['city'], $address);
            $this->sendMessage($chatId, $template, $this->getReportKeyboard());
            usleep(300000);
            $this->sendMessage(
                $chatId,
                "📍 判斷地點：{$cityData['city']}\n\n"
                ."🔗 線上檢舉：\n{$cityData['url']}\n\n"
                .'📞 簡訊報案電話：',
                $this->getReportKeyboard()
            );
            usleep(300000);
            $this->sendMessage($chatId, $cityData['phone'], $this->getReportKeyboard());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Reverse geocoding failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);

            $this->sendMessage($chatId, '⚠️ 反查地址發生錯誤，請稍後再試。', $this->getReportKeyboard());
        }
    }

    protected function getReportKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    [
                        'text' => '📍 取得目前位置',
                        'request_location' => true,
                    ],
                ],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    protected function sendReportIntro(int|string $chatId): void
    {
        $intro = "🚨 交通檢舉助手\n\n請直接輸入違規地址，\n或點擊下方按鈕分享目前位置。";
        $this->sendMessage($chatId, $intro, $this->getReportKeyboard());
    }

    protected function sendMessage(int|string $chatId, string $message, ?array $keyboard = null): void
    {
        $this->apiClient->sendMessage((string) $chatId, $message, $keyboard);
    }

    public function looksLikeLocation(string $text): bool
    {
        if (preg_match('/(市|區|路|街|巷|號)/u', $text)) {
            return true;
        }

        if (preg_match('/\d{1,2}°\d{1,2}\'[^\n]*[NS]/', $text)) {
            return true;
        }

        if (preg_match('/-?\d+\.\d+\s*,\s*-?\d+\.\d+/', $text)) {
            return true;
        }

        return false;
    }

    public function detectCity(string $text): ?array
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

    public function buildReportTemplate(string $city, string $locationText): string
    {
        return "違規地點：\n"
            ."{$locationText}\n\n"
            ."違規情形：\n"
            ."車號 XXX-XXXX 違規停車，\n"
            ."已嚴重影響行人通行及出入動線，\n"
            ."造成用路人不便與安全疑慮。\n\n"
            .'請派員前往查處並依法取締，謝謝。';
    }

    public function normalizeAddressText(string $text): string
    {
        return preg_replace('/^\s*\d{3,5}\s*/u', '', $text);
    }
}
