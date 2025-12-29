<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\LineNotify;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use App\Models\JobChickpt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use App\Models\DeliveryCoupon;

class ScraperDeliveryCoupon extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-delivery-coupon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public $jobs = [];
    protected $lineNotify;
    protected $telegramService;
    protected $seleniumService;
    protected $jobTexts;
    protected $chatId = '-5055523902'; // 暫時先用全域變數 // 日後聊天室擴充再更改

    public function __construct(LineNotify $lineNotify, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotify      = $lineNotify;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    /**
     * Execute the console command.
     */


    public function handle()
    {
        $webDriver = $this->seleniumService->openWebDriver();

        $url = 'https://www.callingtaiwan.com.tw/%E5%A4%96%E9%80%81%E5%84%AA%E6%83%A0%E7%B8%BD%E6%95%B4%E7%90%86-foodpanda-ubereats/';

        $webDriver->get($url);
        sleep(10); // Cloudflare

        $coupons = [];

        $headers = $webDriver->findElements(
            WebDriverBy::cssSelector('.entry-content h2')
        );

        $targetKeyword = '首購優惠碼/折扣碼/信用卡優惠';

        $newCoupons = [];

        foreach ($headers as $header) {

            $title = trim($header->getText());

            if (mb_strpos($title, $targetKeyword) === false) {
                continue;
            }

            try {
                $table = $header->findElement(
                    WebDriverBy::xpath('following::table[1]')
                );
            } catch (\Exception $e) {
                continue;
            }

            $rows = $table->findElements(
                WebDriverBy::cssSelector('tbody tr')
            );

            foreach ($rows as $index => $row) {

                if ($index === 0) continue; // 表頭

                $cols = $row->findElements(WebDriverBy::tagName('td'));
                if (count($cols) < 3) continue;

                try {
                    $a = $cols[2]->findElement(WebDriverBy::tagName('a'));
                } catch (\Exception $e) {
                    continue;
                }

                $coupons[] = [
                    'section_title' => $title,
                    'expire_date'   => trim($cols[0]->getText()),
                    'content'       => trim($cols[1]->getText()),
                    'code'          => trim($a->getText()),
                    'link'          => trim($a->getAttribute('href')),
                ];
            }
        }

        $webDriver->quit();

        foreach ($coupons as $coupon) {

            if ($this->isExpired($coupon['expire_date'])) {
                continue;
            }
            // section_title + content
            $contentHash = hash(
                'sha256',
                $coupon['section_title'] . '|' . $coupon['content']
            );

            // 已經推播 / 存過就跳過
            if (DeliveryCoupon::where('content_hash', $contentHash)->exists()) {
                continue;
            }

            // 判斷平台
            $platform = DeliveryCoupon::detectPlatform($coupon['section_title']);

            // 存 DB
            DeliveryCoupon::create([
                'section_title' => $coupon['section_title'],
                'platform'      => $platform,
                'expire_date'   => $coupon['expire_date'],
                'content'       => $coupon['content'],
                'code'          => $coupon['code'],
                'link'          => $coupon['link'],
                'content_hash'  => $contentHash,
            ]);

            // 留給推播用
            $coupon['platform'] = $platform;
            $newCoupons[] = $coupon;
        }

        if (!empty($newCoupons)) {

            $skipPlatforms = [
                '蝦皮美食外送',
                'Deliveroo',
            ];

            foreach ($newCoupons as $index => $coupon) {

                $platform = $coupon['platform'];

                // 停止營運的平台
                if (in_array($platform, $skipPlatforms, true)) {
                    $this->info("Skip platform push: {$platform}");
                    continue;
                }

                $message = $this->formatPlatformCouponMessage(
                    $platform,
                    $coupon,
                    $index + 1
                );

                $this->telegramService->sendMessage($this->chatId, $message);
                sleep(7);
            }

            $this->info('Telegram pushed. Coupons: ' . count($newCoupons));

        } else {
            $this->info('No new coupons, no push.');
        }

    }

    private function formatPlatformCouponMessage(
        string $platform,
        array $coupon,
        int $index
    ): string
    {
        $text  = $this->getPlatformTitle($platform) . "\n";
        $text .= "📦 第 {$index} 筆\n\n";

        $text .= '📌 ' . $coupon['content'] . "\n";

        if (!empty($coupon['expire_date'])) {
            $text .= '⏰ 期限：' . $coupon['expire_date'] . "\n";
        }

        if (!empty($coupon['code'])) {
            $text .= '🎟 優惠碼：' . $coupon['code'] . "\n";
        }

        // 若未來要開 link 再打開即可
        // if (!empty($coupon['link'])) {
        //     $text .= '🔗 ' . $coupon['link'] . "\n";
        // }

        return $text;
    }

    private function isExpired(?string $expireDate): bool
    {
        if (empty($expireDate)) {
            return false;
        }

        // 「兌完為止」視為未過期
        if (mb_strpos($expireDate, '兌完') !== false) {
            return false;
        }

        // 嘗試解析日期
        try {
            $date = Carbon::createFromFormat('Y/m/d', trim($expireDate));
        } catch (\Exception $e) {
            return false;
        }

        return $date->lt(Carbon::today());
    }

    private function getPlatformTitle(string $platform): string
    {
        return match ($platform) {
            'UberEats' => '🍔 Uber Eats｜優惠',
            'foodpanda' => '🐼 foodpanda｜優惠',
            'foodomo' => '🍱 foodomo｜優惠',
            '蝦皮美食外送' => '🦐 蝦皮美食外送｜優惠',
            'Deliveroo' => '🚴 Deliveroo｜優惠',
            default => '🍽 外送平台｜優惠',
        };
    }

}
