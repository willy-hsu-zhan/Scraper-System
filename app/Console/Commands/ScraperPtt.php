<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\JobPtt;
use App\Services\LineNotify;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use Illuminate\Support\Facades\File;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;

class ScraperPtt extends Command {
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

    protected $lineNotify;
    protected $chatId = '-4664862172'; // 暫時先用全域變數 // 日後聊天室擴充再更改
    protected $telegramService;
    protected $seleniumService;
    protected $jobTexts;

    public function __construct(LineNotify $lineNotify, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotify      = $lineNotify;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    private function fliterPlace(string $notFilterPlace): string
    {
        $filterPlace = '';

        foreach( $this->locations as $location )
        {
            if( mb_strpos($notFilterPlace, $location) !== false )
            {
                $filterPlace = $location;
                break;
            }
        }
        if( empty($filterPlace) )
        {
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

        try
        {
            $webDriver->get($baseUrl);

            $jobs    = [];
            $maxPage = 2;

            for( $currentPage = 1; $currentPage <= $maxPage; $currentPage++ )
            {
                $jobElements = $webDriver->findElements(WebDriverBy::cssSelector('.r-ent'));

                foreach( $jobElements as $element )
                {
                    $linkElement = $element->findElement(WebDriverBy::cssSelector('.title a'));
                    $link        = $linkElement->getAttribute('href');
                    $title       = $linkElement->getText();
                    $author      = $element->findElement(WebDriverBy::cssSelector('.author'))->getText();
                    //$date        = $element->findElement(WebDriverBy::cssSelector('.date'))->getText();
                    $date = Carbon::now()->toDateString();

                    $jobs[] = [
                        'title'   => trim($title),
                        'link'    => $originUrl . trim($link),
                        'company' => trim($author),
                        'date'    => trim($date),
                    ];
                }

                try
                {
                    $nextPageLink = $webDriver->findElement(WebDriverBy::linkText('‹ 上頁'));
                    $nextPageLink->click();
                }
                catch (Exception $e)
                {
                    Log::info('No more pages to scrape.');
                    break;
                }
            }

            foreach( $jobs as $job )
            {
                if (!JobPtt::where('link', $job['link'])->exists()) {
                    JobPtt::create($job);
                    $this->jobTexts[] = $this->formatJobMessage($job);
                }
            }

            $this->sendNotifications();
        }
        catch (Exception $e)
        {
            Log::error('Error during scraping: ' . $e->getMessage());
        }
        finally
        {
            $webDriver->quit();
        }
    }

    public function formatJobMessage(array $job): string
    {
        return "**** PTT ****\n\n" .
            "標題：{$job['title']}\n" .
            "任務連結：{$job['link']}\n" .
            "雇主：{$job['company']}\n" .
            "日期：{$job['date']}\n";
    }

    public function sendNotifications()
    {
        if(!empty($this->jobTexts))
        {
            foreach( $this->jobTexts as $message )
            {
                $this->telegramService->sendMessage($this->chatId, $message);
                sleep(3);
            }

            // 送發財圖
            $this->sendFachaiGIF();
            // 辨別已看過的訊息分隔用
            $this->telegramService->sendMessage($this->chatId, '最後更新時間：' . Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s'));
        }
        else
        {
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

    public function goutteScraperProcess()
    {
        $currentPage = 1; // 默認第一頁為當天工作

        $maxPage = 2; // 因為每天都會跑排程 抓到頂多兩頁就夠了

        $client = new Client();

        $baseUrl = 'https://www.ptt.cc';

        $url = 'https://www.ptt.cc/bbs/part-time'; // part-time版
        // $url = 'https://www.ptt.cc/bbs/part-time/index3950.html';

        $crawler = $client->request('GET', $url);

        while( $currentPage <= $maxPage )
        {
            try
            {
                $link = $crawler->selectLink('上頁')->link(); // 網頁只有上頁or下頁
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('No more pages to load.');
                break;
            }

            $crawler = $client->click($link);

            try
            {
                $jobList = $crawler->filter('.r-ent'); // 抓取job-list獨有的css設定

            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('CSS style or div struct seem has been changed!.');
            }

            /* css排版已改 無法使用
            $jobList->each(function ($jobNode) use (&$jobs, $baseUrl) { // 傳值不能傳參考
                $title   = $jobNode->filter('a')->text();
                $link    = $baseUrl . $jobNode->filter('a')->attr('href');
                $company = $jobNode->filter('.author')->text();
                $salary  = 0; // 進去網頁內的參數也是不定參數 所以直接給他設為0
                $place   = $this->fliterPlace($jobNode->filter('a')->text());
                $date    = $jobNode->filter('.date')->text();

                $jobs[] = [
                    'title'   => trim($title),
                    'link'    => trim($link),
                    'company' => trim($company),
                    'salary'  => trim($salary),
                    'place'   => trim($place),
                    'date'    => trim($date),
                ];
            });
            */

            $jobList->each(function ($jobNode) use (&$jobs, $baseUrl) {
                $linkNode   = $jobNode->filter('.title a');
                $dateNode   = $jobNode->filter('.date');
                $authorNode = $jobNode->filter('.author');

                $link   = $linkNode->count() > 0 ? $baseUrl . $linkNode->attr('href') : null;
                $title  = $linkNode->count() > 0 ? $linkNode->text() : 'Unknown';
                $author = $authorNode->count() > 0 ? $authorNode->text() : 'Unknown';
                $date   = $dateNode->count() > 0 ? $dateNode->text() : 'Unknown';

                // result
                $jobs[] = [
                    'title'   => trim($title),
                    'link'    => trim($link),
                    'company' => trim($author),
                    'date'    => trim($date),
                ];
            });

            ++$currentPage;
        }

        /*
        $client = new Client();

        $url = 'https://www.ptt.cc/bbs/Soft_Job'; //軟工版

        $currentPage = 1; // 默認第一頁為當天工作

        $crawler = $client->request('GET', $url);

        while( $currentPage <= $maxPage )
        {
            try
            {
                $link = $crawler->selectLink('上頁')->link(); // 網頁只有上頁or下頁
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('No more pages to load.');
                break;
            }

            $crawler = $client->click($link);

            try
            {
                $jobList = $crawler->filter('.r-ent'); // 抓取job-list獨有的css設定
            }
            catch (Exception $e)
            {
                Log::error($e->getMessage());
                Log::error('CSS style or div struct seem has been changed!.');
            }

            $jobList->each(function ($jobNode) use (&$jobs, $baseUrl) { // 傳值不能傳參考
                $titleNode = $jobNode->filter('a');
                $title     = $titleNode->count() > 0 ? $titleNode->text() : '';

                $linkNode = $jobNode->filter('a');
                $link     = $linkNode->count() > 0 ? $baseUrl . $linkNode->attr('href') : '';

                $company = $jobNode->filter('.author')->text();
                $salary  = 0; // 進去網頁內的參數也是不定參數 所以直接給他設為0
                $place   = $this->fliterPlace($title); // 使用 $jobNode->filter('a')->text() 來過濾地點
                $date    = $jobNode->filter('.date')->text();

                if( mb_strpos($title, '徵才') !== false ) // 因為會有其他討論串，只抓title是徵才的才是工作
                {
                    $jobs[] = [
                        'title'   => trim($title),
                        'link'    => trim($link),
                        'company' => trim($company),
                        'salary'  => trim($salary),
                        'place'   => trim($place),
                        'date'    => trim($date),
                    ];
                }
            });

            ++$currentPage;
        }
        */

        foreach( $jobs as $key => $job )
        {
            if( isset($job['place']) && $job['place'] == '公告' )
            {
                unset($jobs[$key]);
            }
            if( mb_strpos($job['title'], '已徵到') !== false )
            {
                unset($jobs[$key]);
            }
        }

        $jobs = array_values($jobs);

        $sendNotifyText = "";

        foreach( $jobs as $job )
        {
            $date = Carbon::now()->toDateString();

            if( !JobPtt::where('link', $job['link'])->first() )
            {
                if( empty($sendNotifyText) )
                    $sendNotifyText .= "\n" . "PTT任務列表" . "\n" . "\n";

                JobPtt::create([
                    'title'   => isset($job['title']) ? $job['title'] : '',
                    'link'    => $job['link'],
                    'company' => isset($job['company']) ? $job['company'] : '',
                    'salary'  => isset($job['salary']) ? $job['salary'] : 0,
                    'place'   => isset($job['place']) ? $job['place'] : '',
                    'date'    => $date,
                ]);

                $sendNotifyText .= '標題：' . (isset($job['title']) ? $job['title'] : '') . "\n";
                $sendNotifyText .= '任務連結：' . $job['link'] . "\n";
                $sendNotifyText .= '雇主：' . (isset($job['company']) ? $job['company'] : '') . "\n";
                $sendNotifyText .= '工作地方：' . (isset($job['place']) ? $job['place'] : '') . "\n";
                $sendNotifyText .= '薪水：' . (isset($job['salary']) ? $job['salary'] : 0) . "\n";

                $this->jobTexts[] = [
                    '**** PTT ****' . "\n\n" .
                    '標題：' . (isset($job['title']) ? $job['title'] : '') . "\n" .
                    '任務連結：' . $job['link'] . "\n" .
                    '雇主：' . (isset($job['company']) ? $job['company'] : '') . "\n"
                    //'工作地方：' . (isset($job['place']) ? $job['place'] : '') . "\n".
                    //'薪水：' . (isset($job['salary']) ? $job['salary'] : 0) . "\n"
                ];
            }

            if( !empty($sendNotifyText) )
                $sendNotifyText .= "\n" . "\n";
        }

        // 暫時先隱藏line notify
        // if( !empty($sendNotifyText) ) // send line notify
        // {
        //     $this->info('save data success');

        //     $response = $this->lineNotify->sendMessage($sendNotifyText);

        //     $data = json_decode($response);

        //     if( $data->status != '200' && $data->message != 'ok' )
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

        if( !empty($this->jobTexts) ) // send telegram notify
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
