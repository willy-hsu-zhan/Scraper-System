<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use App\Models\BirthdayDiscount;
use Illuminate\Support\Carbon;
use Facebook\WebDriver\WebDriverBy;


class ScraperBirthdayDiscount extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-birthday-discount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public $jobs = [];
    protected $lineNotifyService;
    protected $telegramService;
    protected $seleniumService;
    protected $jobTexts;
    protected $chatId = '-5445385394'; // 暫時先用全域變數 // 日後聊天室擴充再更改

    public function __construct(TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $url = 'https://happybirthday.com.tw/list';

        $webDriver = $this->seleniumService->openWebDriver();

        $webDriver->get($url);

        $totalCount = 0;

        try {
            $footerText = $webDriver->findElement(
                WebDriverBy::cssSelector('.view-footer .text-center')
            )->getText();

            if (preg_match('/共計\s*(\d+)\s*筆/', $footerText, $m)) {
                $totalCount = (int)$m[1] - 1;
            }
        } catch (\Exception $e) {

        }
        $perPage = 20;
        $totalPages = $totalCount > 0
            ? (int) ceil($totalCount / $perPage)
            : 1;

        $items = $webDriver->findElements(
            WebDriverBy::cssSelector('.col-xs-12.col-sm-8.col-md-8')
        );

        $allEvents = [];

        for ($page = 0; $page < $totalPages; $page++) {
            $pageUrl = 'https://happybirthday.com.tw/list?page=' . $page;
            $this->info("Scanning page: {$page}");

            $webDriver->get($pageUrl);
            sleep(2); // 給 JS 時間（很重要）

            $items = $webDriver->findElements(
                WebDriverBy::cssSelector('.col-xs-12.col-sm-8.col-md-8')
            );

            foreach ($items as $item) {

                try {
                    $a = $item->findElement(WebDriverBy::cssSelector('h2 a'));
                    $title = trim($a->getText());
                    $link  = 'https://happybirthday.com.tw' . $a->getAttribute('href');
                } catch (\Exception $e) {
                    continue;
                }

                // 截止日
                $expireDate = null;
                try {
                    $finalText = $item->findElement(
                        WebDriverBy::cssSelector('span.final')
                    )->getText();

                    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $finalText, $m)) {
                        $expireDate = $m[1];
                    }
                } catch (\Exception $e) {}

                $desc = '';
                try {
                    $desc = trim(
                        $item->findElement(WebDriverBy::tagName('p'))->getText()
                    );
                } catch (\Exception $e) {}

                $allEvents[] = [
                    'title'       => $title,
                    'link'        => $link,
                    'expire_date' => $expireDate,
                    'description' => $desc,
                ];
            }
        }

        $newEvents = [];

        foreach ($allEvents as $event) {

            // title hash 去重
            $titleHash = BirthdayDiscount::makeTitleHash($event['title']);

            $record = BirthdayDiscount::where('title_hash', $titleHash)->first();

            if ($record) {
                // 已存在就更新內容（避免內容改了抓不到）
                $record->update([
                    'expire_date' => $event['expire_date'],
                    'description' => $event['description'],
                    'link'        => $event['link'],
                    'is_expired'  => false,
                ]);
                continue;
            }

            // 新資料
            BirthdayDiscount::create([
                'title'        => $event['title'],
                'title_hash'   => $titleHash,
                'link'         => $event['link'],
                'expire_date'  => $event['expire_date'],
                'description'  => $event['description'],
                'is_expired'   => false,
                'is_push'      => 0,
            ]);

            $newEvents[] = $event;
        }

        $this->pushMonthlyBirthdayDiscount();
        $webDriver->quit();
    }

    private function pushMonthlyBirthdayDiscount()
    {
        $events = BirthdayDiscount::where('is_push', 0)
            ->orderBy('expire_date')
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        foreach ($events as $index => $event) {

            $text  = "🎂 本月壽星優惠整理（" . Carbon::now()->format('Y/m') . "）\n";
            $text .= "📦 第 " . ($index + 1) . " 筆\n\n";
            $text .= '🎉 ' . $event->title . "\n";
            $text .= '⏰ 截止：' . $event->expire_date . "\n";
            $text .= '🔗 ' . $event->link . "\n";

            // 推播
            $this->telegramService->sendMessage($this->chatId, $text);
            sleep(7);

            // 推播成功後，立刻標記 is_push
            $event->update(['is_push' => true]);
        }
    }
}
