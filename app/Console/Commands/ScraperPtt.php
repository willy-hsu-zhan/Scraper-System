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

    private function filterPlace(string $title): string
    {
        $locations = [
            '遠端',
            '全國',
            '多區',
            '台北',
            '新北',
            '桃園',
            '台中',
            '台南',
            '高雄',
            '基隆',
            '新竹',
            '苗栗',
            '彰化',
            '南投',
            '雲林',
            '嘉義',
            '屏東',
            '宜蘭',
            '花蓮',
            '台東',
            '澎湖',
            '金門',
        ];


        foreach ($locations as $location) {


            if (mb_strpos($title, $location) !== false) {

                return $location;
            }
        }


        return '未分類';
    }

    private function parseSalary(string $title): ?string
    {
        // 時薪
        if (preg_match('/時薪\s?(\d+)/u', $title, $match)) {
            return '時薪 ' . $match[1];
        }


        // 月薪
        if (preg_match('/月薪\s?(\d+)/u', $title, $match)) {
            return '月薪 ' . $match[1];
        }


        // 日薪
        if (preg_match('/日薪\s?(\d+)/u', $title, $match)) {
            return '日薪 ' . $match[1];
        }


        // 薪資數字，例如 200/hr、500元
        if (preg_match('/(\d+)\s?(元|\/hr|\/小時|\/時)/u', $title, $match)) {
            return $match[1] . '元';
        }


        if (mb_strpos($title, '面議') !== false) {
            return '面議';
        }


        return null;
    }

    public function handle()
    {
        $originUrl = 'https://www.pttweb.cc';
        $baseUrl   = 'https://www.pttweb.cc/bbs/part-time/index.html';

        $webDriver = $this->seleniumService->openWebDriver();

        try {

            $jobs = [];
            $maxPage = 2;


            // 初始化
            $webDriver->get($baseUrl);


            // 等待 Vue render
            $webDriver->wait(15)->until(
                function ($driver) {
                    return count(
                        $driver->findElements(
                            WebDriverBy::cssSelector('a.e7-article-default')
                        )
                    ) > 0;
                }
            );


            for ($currentPage = 1; $currentPage <= $maxPage; $currentPage++) {


                $this->info(
                    '目前網址: ' . $webDriver->getCurrentURL()
                );


                $articleElements = $webDriver->findElements(
                    WebDriverBy::cssSelector('a.e7-article-default')
                );


                $this->info(
                    '找到文章數: ' . count($articleElements)
                );


                foreach ($articleElements as $element) {


                    try {


                        $title = trim(
                            $element->findElement(
                                WebDriverBy::cssSelector('.e7-title')
                            )->getText()
                        );


                        $link = $element->getAttribute('href');


                        // 作者
                        $author = '';

                        try {

                            $author = trim(
                                $element->findElement(
                                    WebDriverBy::xpath(
                                        "./ancestor::div[contains(@class,'e7-container')]//div[contains(@class,'e7-meta-container')]//a"
                                    )
                                )->getText()
                            );
                        } catch (Exception $e) {
                        }



                        // 日期
                        $date = now()->format('Y-m-d');


                        try {

                            $dateText = $element->findElement(
                                WebDriverBy::xpath(
                                    "./ancestor::div[contains(@class,'e7-container')]//div[contains(@class,'e7-grey-text')]//span[last()]"
                                )
                            )->getText();


                            if (preg_match('/\d{4}\/\d{2}\/\d{2}/', $dateText)) {


                                $date = Carbon::createFromFormat(
                                    'Y/m/d H:i',
                                    trim($dateText)
                                )->format('Y-m-d');
                            }
                        } catch (Exception $e) {
                        }



                        // 地區
                        $place = $this->filterPlace($title);


                        // 薪資
                        $salary = $this->parseSalary($title);



                        $jobs[] = [

                            'title'   => $title,

                            'link'    => $originUrl . $link,

                            'company' => $author,

                            'date'    => $date,

                            'place'   => $place,

                            'salary'  => $salary,

                        ];
                    } catch (Exception $e) {


                        Log::warning(
                            'Parse article error: ' . $e->getMessage()
                        );


                        continue;
                    }
                }



                // 下一頁
                if ($currentPage < $maxPage) {


                    try {


                        $nextPageLink = $webDriver->findElement(
                            WebDriverBy::linkText('‹ 上頁')
                        );


                        $nextPageLink->click();


                        sleep(3);
                    } catch (Exception $e) {


                        Log::info(
                            'No more pages.'
                        );


                        break;
                    }
                }
            }




            /**
             * DB 儲存
             */
            foreach ($jobs as $job) {


                if (!JobPtt::where('link', $job['link'])->exists()) {


                    JobPtt::create($job);


                    $this->jobTexts[] =
                        $this->formatJobMessage($job);
                }
            }



            $this->sendNotifications();



            $this->info(
                '完成，共抓取 ' . count($jobs) . ' 筆'
            );
        } catch (Exception $e) {


            Log::error(
                'Scraper Error: ' . $e->getMessage()
            );


            $this->error(
                $e->getMessage()
            );
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
