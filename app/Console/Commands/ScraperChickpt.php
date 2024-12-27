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

class ScraperChickpt extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-chickpt';

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
    protected $chatId = '-4664862172'; // 暫時先用全域變數 // 日後聊天室擴充再更改

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
        $baseUrl   = 'https://www.chickpt.com.tw';

        // 連接到 Selenium WebDriver
        $webDriver = $this->seleniumService->openWebDriver();

        // 開啟網頁
        $webDriver->get('https://www.chickpt.com.tw/cases?is_address_no_limit=1');

        // 設定頁碼與初始資料
        $currentPage = 1; // 預設為第1頁
        $jobs        = [];

        while( true )
        {
            try
            {
                // 檢查是否有 QR code 的彈出條，如果有，點擊關閉
                try
                {
                    $webDriver->wait(10)->until(
                        WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('qrcodebar-pc-closebtn'))
                    );
                    $closeButton = $webDriver->findElement(WebDriverBy::id('qrcodebar-pc-closebtn'));
                    $closeButton->click();
                    Log::info("QRCode bar closed.");
                }
                catch (Exception $e)
                {
                    Log::info("No QR code bar to close or error closing: " . $e->getMessage());
                }

                // 查找並點擊分頁連結
                $link = $webDriver->findElement(WebDriverBy::linkText((string)$currentPage));
                $link->click();

                try
                {
                    $webDriver->wait(10)->until(
                        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::linkText((string)$currentPage))
                    );
                    $webDriver->wait(10)->until(
                        WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::linkText((string)$currentPage))
                    );
                }
                catch (Exception $e)
                {
                    Log::error('Error clicking element on page ' . $currentPage . ': ' . $e->getMessage());
                }

                // 取得 job 列表
                $jobElements = $webDriver->findElements(WebDriverBy::cssSelector('.jobs-wrap ul#job-list.job-list.show li'));

                foreach( $jobElements as $jobNode )
                {
                    $title       = $jobNode->findElement(WebDriverBy::cssSelector('h2'))->getText();
                    $link        = $jobNode->findElement(WebDriverBy::cssSelector('a'))->getAttribute('href');
                    $company     = $jobNode->findElement(WebDriverBy::cssSelector('.ellipsis-job-company'))->getText();
                    $salary      = $jobNode->findElement(WebDriverBy::cssSelector('.salary'))->getText();
                    $place       = $jobNode->findElement(WebDriverBy::cssSelector('.place'))->getText();
                    $update_time = $jobNode->findElement(WebDriverBy::cssSelector('.date-time'))->getText();

                    $link = $baseUrl . $link;
                    // 檢查是否已存在資料庫中
                    if( !JobChickpt::where('link', $link)->first() )
                    {
                        $date = Carbon::now()->toDateString();
                        JobChickpt::create([
                            'title'   => $title,
                            'link'    => $link,
                            'company' => $company,
                            'salary'  => $salary,
                            'place'   => $place,
                            'date'    => $date,
                        ]);

                        // 儲存爬取的工作資訊
                        $jobs[] = [
                            'title'       => trim($title),
                            'link'        => trim($link),
                            'company'     => trim($company),
                            'salary'      => trim($salary),
                            'place'       => trim($place),
                            'update_time' => trim($update_time),
                        ];
                    }
                }

                if( $currentPage >= 3 )
                { // 限制掃描頁數以避免過多請求
                    break;
                }

                // 增加頁碼
                ++$currentPage;
            }
            catch (Exception $e)
            {
                Log::error('Error scraping page ' . $currentPage . ': ' . $e->getMessage());
                break; // 跳出循環
            }
        }

        $webDriver->quit(); // 完成後退出 WebDriver

        // 傳送通知
        if( !empty($jobs) )
        {
            $this->sendJobNotifications($jobs);
        }
    }

    public function sendFachaiGIF()
    {
        $path     = public_path('assets/video/fachai/');
        $files    = File::files($path);
        $fileName = [];

        foreach( $files as $file )
        {
            $fileName[] = $file->getFilename();
        }
        if( !empty($fileName) )
        {
            $randomFile     = $fileName[array_rand($fileName)];
            $randomFilePath = $path . $randomFile;
            $this->telegramService->sendDocument($this->chatId, $randomFilePath, $randomFile);
        }
    }

    public function sendJobNotifications($jobs)
    {
        $sendNotifyText = '';
        foreach( $jobs as $job )
        {
            $sendNotifyText = "**** 小雞上工 **** \n\n";
            $sendNotifyText .= '標題：' . $job['title'] . "\n";
            $sendNotifyText .= '任務連結：' . $job['link'] . "\n";
            $sendNotifyText .= '雇主：' . $job['company'] . "\n";
            $sendNotifyText .= '工作地方：' . $job['place'] . "\n";
            $sendNotifyText .= '薪水：' . $job['salary'] . "\n\n";

            $this->telegramService->sendMessage($this->chatId, $sendNotifyText);
            sleep(3);
            $sendNotifyText = '';
        }

        $this->sendFachaiGIF(); // 送發財圖
        
        $infoText = '最後更新時間：' . Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s');
        $this->telegramService->sendMessage($this->chatId, $infoText);
    }

    public function goutteScraperProcess()
    {
        $currentPage = 1; // 小雞上工任務頁預設為第一頁

        $client = new Client();

        $url = 'https://www.chickpt.com.tw/cases?is_address_no_limit=1';

        $crawler = $client->request('GET', $url); // 默認第一頁

        while( true )
        {
            try
            {
                $link = $crawler->selectLink($currentPage)->link();
            }
            catch (Exception $e)
            {
                if( $currentPage >= 3 ) break; // 降低loading 不全掃 因為每半小時都會跑

                Log::error($e->getMessage());
                Log::error('No more pages to load.');
                break;
            }

            $crawler = $client->click($link);

            try
            {
                $jobList = $crawler->filter('.jobs-wrap ul#job-list.job-list.show li'); // 抓取job-list獨有的css設定
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('CSS style or div struct seem has been changed!.');
            }

            // Loop through each job
            $jobList->each(function ($jobNode) use (&$jobs) { // 傳值不能傳參考
                $title       = $jobNode->filter('h2')->text();
                $link        = $jobNode->filter('a')->attr('href');
                $company     = $jobNode->filter('.ellipsis-job-company')->text();
                $salary      = $jobNode->filter('.salary')->text();
                $place       = $jobNode->filter('.place')->text();
                $update_time = $jobNode->filter('.date-time')->text();

                $jobs[] = [
                    'title'       => trim($title),
                    'link'        => trim($link),
                    'company'     => trim($company),
                    'salary'      => trim($salary),
                    'place'       => trim($place),
                    'update_time' => trim($update_time),
                ];
            });

            ++$currentPage;
        }

        $sendNotifyText = "";

        foreach( $jobs as $job )
        {
            $date = Carbon::now()->toDateString();

            if( !JobChickpt::where('link', $job['link'])->first() )
            {
                if( empty($sendNotifyText) )
                    $sendNotifyText .= "\n" . "小雞上工任務列表" . "\n" . "\n";

                JobChickpt::create([
                    'title'   => $job['title'],
                    'link'    => $job['link'],
                    'company' => $job['company'],
                    'salary'  => $job['salary'],
                    'place'   => $job['place'],
                    'date'    => $date,
                ]);

                $sendNotifyText .= '標題：' . $job['title'] . "\n";
                $sendNotifyText .= '任務連結：' . $job['link'] . "\n";
                $sendNotifyText .= '雇主：' . $job['company'] . "\n";
                $sendNotifyText .= '工作地方：' . $job['place'] . "\n";
                $sendNotifyText .= '薪水：' . $job['salary'] . "\n";

                $this->jobTexts[] = [
                    '**** 小雞上工 ****' . "\n\n" .
                    '標題：' . $job['title'] . "\n" .
                    '任務連結：' . $job['link'] . "\n" .
                    '雇主：' . $job['company'] . "\n" .
                    '工作地方：' . $job['place'] . "\n" .
                    '薪水：' . $job['salary'] . "\n"
                ];
            }
            if( !empty($sendNotifyText) )
                $sendNotifyText .= "\n" . "\n";
        }

        foreach( $jobs as $job )
        {
            $date            = Carbon::now()->toDateString();

            if( !JobChickpt::where('link', $job['link'])->first() )
            {
                if( empty($sendNotifyText) )
                    $sendNotifyText .= "\n" . "小雞上工任務列表" . "\n" . "\n";

                JobChickpt::create([
                    'title'   => $job['title'],
                    'link'    => $job['link'],
                    'company' => $job['company'],
                    'salary'  => $job['salary'],
                    'place'   => $job['place'],
                    'date'    => $date,
                ]);

                $sendNotifyText .= '標題：' . $job['title'] . "\n";
                $sendNotifyText .= '任務連結：' . $job['link'] . "\n";
                $sendNotifyText .= '雇主：' . $job['company'] . "\n";
                $sendNotifyText .= '工作地方：' . $job['place'] . "\n";
                $sendNotifyText .= '薪水：' . $job['salary'] . "\n";

                $this->jobTexts[] = [
                    '**** 小雞上工 ****' . "\n\n" .
                    '標題：' . $job['title'] . "\n" .
                    '任務連結：' . $job['link'] . "\n" .
                    '雇主：' . $job['company'] . "\n" .
                    '工作地方：' . $job['place'] . "\n" .
                    '薪水：' . $job['salary'] . "\n"
                ];
            }

            if( !empty($sendNotifyText) )
            {
                $sendNotifyText .= "\n" . "\n";
            }
        }

        // 暫時先隱藏line notify
        // if (!empty($sendNotifyText))
        // {
        //     $this->info('send line notify start');

        //     $response = $this->lineNotify->sendMessage($sendNotifyText);

        //     $data = json_decode($response);

        //     if ($data->status != '200' && $data->message != 'ok')
        //     {
        //         Log::error('send notify error! ' . $data->message);
        //     }
        //     else
        //     {
        //         $this->info('send notify success');
        //     }
        // }
        // else
        // {
        //     $this->info('no recent job period!');
        // }

        if( !empty($this->jobTexts) )
        {
            foreach( $this->jobTexts as $index => $jobText )
            {
                $message = implode("\n", $jobText);
                $this->telegramService->sendMessage($this->chatId, $message);
                sleep(3);
            }

            // 送發財圖
            $this->sendFachaiGIF();
            // 辨別已看過的訊息分隔用
            $infoText = '最後更新時間：' . Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s');
            $this->telegramService->sendMessage($this->chatId, $infoText);
        }
    }
}
