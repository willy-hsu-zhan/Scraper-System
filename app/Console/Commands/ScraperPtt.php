<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\JobPtt;
use App\Services\LineNotifyService;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use Illuminate\Support\Facades\File;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;

class ScraperPtt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-ptt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public $locations = ['遠端', '多區', '台北', '新北', '桃園', '台中', '台南', '高雄', '基隆', '新竹', '新竹', '苗栗', '彰化', '南投', '雲林', '嘉義', '嘉義', '屏東', '宜蘭', '花蓮', '台東', '澎湖', '金門', '連江'];

    protected $lineNotifyService;
    protected $chatId = '-5234655807'; // 暫時先用全域變數 // 日後聊天室擴充再更改
    protected $telegramService;
    protected $seleniumService;
    protected $jobTexts;

    public function __construct(LineNotifyService $lineNotifyService, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotifyService      = $lineNotifyService;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    private function fliterPlace(string $notFilterPlace): string
    {
        $filterPlace = '';

        foreach ($this->locations as $location) {
            if (mb_strpos($notFilterPlace, $location) !== false) {
                $filterPlace = $location;
                break;
            }
        }
        if (empty($filterPlace)) {
            $filterPlace = mb_substr($notFilterPlace, 1, 2);
        }

        return $filterPlace;
    }

    public function handle()
    {
        //$originUrl = 'https://www.ptt.cc';
        $originUrl = 'https://www.pttweb.cc';
        $baseUrl   = 'https://www.pttweb.cc/bbs/part-time/index.html';
        $webDriver = $this->seleniumService->openWebDriver();

        try {
            $jobs    = [];
            $maxPage = 2;

            for ($currentPage = 1; $currentPage <= $maxPage; $currentPage++) {
                $jobElements = $webDriver->findElements(
                    WebDriverBy::cssSelector('.e7-container')
                );

                $webDriver->get($baseUrl);

                sleep(5);

                $this->info('目前網址: '.$webDriver->getCurrentURL());
                $this->info('標題: '.$webDriver->getTitle());

                $this->info('找到文章數: '.count($jobElements));

                foreach ($jobElements as $element) {
                    try {
                        $linkElement = $element->findElement(
                            WebDriverBy::cssSelector('.e7-title')
                        );

                        $link = $element->findElement(
                            WebDriverBy::cssSelector('a.e7-article-default')
                        )->getAttribute('href');

                        $title = $linkElement->getText();

                        $author = $element->findElement(
                            WebDriverBy::cssSelector('.e7-meta-container a')
                        )->getText();

                        $dateElements = $element->findElements(
                            WebDriverBy::cssSelector('.e7-grey-text span')
                        );

                        $date = null;

                        foreach ($dateElements as $span) {
                            $text = trim($span->getText());

                            if (preg_match('/\d{4}\/\d{2}\/\d{2}/', $text)) {
                                $date = Carbon::createFromFormat('Y/m/d H:i', $text)
                                    ->format('Y-m-d');

                                break;
                            }
                        }

                        $jobs[] = [
                            'title'   => trim($title),
                            'link'    => $originUrl . trim($link),
                            'company' => trim($author),
                            'date'    => trim($date),
                        ];
                    } catch (Exception $e) {
                        continue;
                    }
                }
                try {
                    $nextPageLink = $webDriver->findElement(WebDriverBy::linkText('‹ 上頁'));
                    $nextPageLink->click();
                } catch (Exception $e) {
                    Log::info('No more pages to scrape.');
                    break;
                }
            }

            foreach ($jobs as $job) {
                if (!JobPtt::where('link', $job['link'])->exists()) {
                    JobPtt::create($job);
                    $this->jobTexts[] = $this->formatJobMessage($job);
                }
            }

            $this->sendNotifications();
        } catch (Exception $e) {
            Log::error('Error during scraping: ' . $e->getMessage());
        } finally {
            $webDriver->quit();
        }
    }

    public function formatJobMessage(array $job): string
    {
        return <<<TEXT
        🐥 PTT 打工板

        📌 {$job['title']}
        👤 {$job['company']}
        📅 {$job['date']}
        🔗 {$job['link']}

        ──────────────
        TEXT;
    }

    public function sendNotifications()
    {
        if (!empty($this->jobTexts)) {
            foreach ($this->jobTexts as $message) {
                $this->telegramService->sendMessage($this->chatId, $message);
                sleep(3);
            }

            // 送發財圖
            //$this->sendFachaiGIF();
            // 辨別已看過的訊息分隔用
            $this->telegramService->sendMessage($this->chatId, '最後更新時間：' . Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s'));
        } else {
            //$this->sendPeriodNoJobMsg();
        }
    }
    public function sendPeriodNoJobMsg()
    {
        $errorMessage = "🚨 **PTT通知** 🚨\n\n";
        $errorMessage .= "時間：" .
            Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s') .
            ' ~ ' .
            Carbon::now()->subMinutes(30)->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s') . "\n";
        $errorMessage .= "沒有新的任務！！" . "\n";
        $this->telegramService->sendMessage($this->chatId, $errorMessage);
    }

    public function sendFachaiGIF()
    {
        $path     = public_path('assets/video/fachai/');
        $files    = File::files($path);
        $fileName = [];

        foreach ($files as $file) {
            $fileName[] = $file->getFilename();
        }
        if (!empty($fileName)) {
            $randomFile     = $fileName[array_rand($fileName)];
            $randomFilePath = $path . $randomFile;
            $this->telegramService->sendDocument($this->chatId, $randomFilePath, $randomFile);
        }
    }
}
