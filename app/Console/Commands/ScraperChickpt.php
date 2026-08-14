<?php

namespace App\Console\Commands;

use App\Models\JobChickpt;
use App\Services\LineNotifyService;
use App\Services\Scraper\Chickpt\ChickptScraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Exception;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ScraperChickpt extends Command
{
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

    protected $lineNotifyService;

    protected $telegramService;

    protected $seleniumService;

    protected $jobTexts;

    protected $chatId = TelegramChat::CHICKPT;

    public function __construct(LineNotifyService $lineNotifyService, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotifyService = $lineNotifyService;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $scraper = new ChickptScraper($this->seleniumService);
        $items = $scraper->run();

        if (! empty($items)) {
            foreach ($items as $item) {
                $this->telegramService->sendMessage($this->chatId, $scraper->format($item));
                sleep(3);
            }
        }

        $this->info('完成，共抓取 '.count($items).' 筆');
    }

    public function sumTotalPrice($jobs)
    {
        $totalPrice = 0;

        foreach ($jobs as $job) {
            if (preg_match('/\d+/', $job['salary'], $matches)) {
                $totalPrice += (int) $matches[0];
            }
        }

        return $totalPrice;
    }

    public function sendPeriodNoJobMsg()
    {
        $errorMessage = "🚨 **小雞上工通知** 🚨\n\n";
        $errorMessage .= '時間：'.
            Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s').
            ' ~ '.
            Carbon::now()->subMinutes(30)->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s')."\n";
        $errorMessage .= '沒有新的任務！！'."\n";
        $this->telegramService->sendMessage($this->chatId, $errorMessage);
    }

    public function sendErrorLog($message)
    {
        $errorMessage = "🚨 **錯誤日誌通知** 🚨\n\n";
        $errorMessage .= "錯誤訊息：\n".$message."\n";
        $errorMessage .= '時間：'.Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s')."\n";

        $this->telegramService->sendMessage($this->chatId, $errorMessage);
    }

    public function sendFachaiGIF()
    {
        $path = public_path('assets/video/fachai/');
        $files = File::files($path);
        $fileName = [];

        foreach ($files as $file) {
            $fileName[] = $file->getFilename();
        }
        if (! empty($fileName)) {
            $randomFile = $fileName[array_rand($fileName)];
            $randomFilePath = $path.$randomFile;
            $this->telegramService->sendDocument($this->chatId, $randomFilePath, $randomFile);
        }
    }

    public function sendJobNotifications($jobs)
    {
        $sendNotifyText = '';
        foreach ($jobs as $job) {
            $sendNotifyText = <<<TEXT
            📢 新的工作機會

            📍 {$job['title']}
            🏢 {$job['company']}
            📍 {$job['place']}
            💰 {$job['salary']}
            🌐 {$job['link']}

            ────────────────
            TEXT;

            $this->telegramService->sendMessage($this->chatId, $sendNotifyText);
            sleep(3);
            $sendNotifyText = '';
        }

        $totalPrice = $this->sumTotalPrice($jobs);

        $infoText = '本回任務含金量總共為：'.$totalPrice.'元整';
        $this->telegramService->sendMessage($this->chatId, $infoText);

        $infoText = '最後更新時間：'.Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s');
        $this->telegramService->sendMessage($this->chatId, $infoText);
    }

    public function goutteScraperProcess()
    {
        $currentPage = 1; // 小雞上工任務頁預設為第一頁

        $client = new Client();

        $url = 'https://www.chickpt.com.tw/cases?is_address_no_limit=1';

        $crawler = $client->request('GET', $url); // 默認第一頁

        while (true) {
            try {
                $link = $crawler->selectLink($currentPage)->link();
            } catch (Exception $e) {
                if ($currentPage >= 3) {
                    break;
                } // 降低loading 不全掃 因為每半小時都會跑

                Log::error($e->getMessage());
                Log::error('No more pages to load.');
                break;
            }

            $crawler = $client->click($link);

            try {
                $jobList = $crawler->filter('.jobs-wrap ul#job-list.job-list.show li'); // 抓取job-list獨有的css設定
            } catch (Exception $e) {
                Log::error($e->getMessage());
                Log::error('CSS style or div struct seem has been changed!.');
            }

            // Loop through each job
            $jobList->each(function ($jobNode) use (&$jobs) { // 傳值不能傳參考
                $title = $jobNode->filter('h2')->text();
                $link = $jobNode->filter('a')->attr('href');
                $company = $jobNode->filter('.ellipsis-job-company')->text();
                $salary = $jobNode->filter('.salary')->text();
                $place = $jobNode->filter('.place')->text();
                $update_time = $jobNode->filter('.date-time')->text();

                $jobs[] = [
                    'title' => trim($title),
                    'link' => trim($link),
                    'company' => trim($company),
                    'salary' => trim($salary),
                    'place' => trim($place),
                    'update_time' => trim($update_time),
                ];
            });

            $currentPage++;
        }

        $sendNotifyText = '';

        foreach ($jobs as $job) {
            $date = Carbon::now()->toDateString();

            if (! JobChickpt::where('link', $job['link'])->first()) {
                if (empty($sendNotifyText)) {
                    $sendNotifyText .= "\n".'小雞上工任務列表'."\n"."\n";
                }

                JobChickpt::create([
                    'title' => $job['title'],
                    'link' => $job['link'],
                    'company' => $job['company'],
                    'salary' => $job['salary'],
                    'place' => $job['place'],
                    'date' => $date,
                ]);

                $sendNotifyText .= '標題：'.$job['title']."\n";
                $sendNotifyText .= '任務連結：'.$job['link']."\n";
                $sendNotifyText .= '雇主：'.$job['company']."\n";
                $sendNotifyText .= '工作地方：'.$job['place']."\n";
                $sendNotifyText .= '薪水：'.$job['salary']."\n";

                $this->jobTexts[] = [
                    '**** 小雞上工 ****'."\n\n".
                    '標題：'.$job['title']."\n".
                    '任務連結：'.$job['link']."\n".
                    '雇主：'.$job['company']."\n".
                    '工作地方：'.$job['place']."\n".
                    '薪水：'.$job['salary']."\n",
                ];
            }
            if (! empty($sendNotifyText)) {
                $sendNotifyText .= "\n"."\n";
            }
        }

        foreach ($jobs as $job) {
            $date = Carbon::now()->toDateString();

            if (! JobChickpt::where('link', $job['link'])->first()) {
                if (empty($sendNotifyText)) {
                    $sendNotifyText .= "\n".'小雞上工任務列表'."\n"."\n";
                }

                JobChickpt::create([
                    'title' => $job['title'],
                    'link' => $job['link'],
                    'company' => $job['company'],
                    'salary' => $job['salary'],
                    'place' => $job['place'],
                    'date' => $date,
                ]);

                $sendNotifyText .= '標題：'.$job['title']."\n";
                $sendNotifyText .= '任務連結：'.$job['link']."\n";
                $sendNotifyText .= '雇主：'.$job['company']."\n";
                $sendNotifyText .= '工作地方：'.$job['place']."\n";
                $sendNotifyText .= '薪水：'.$job['salary']."\n";

                $this->jobTexts[] = [
                    '**** 小雞上工 ****'."\n\n".
                    '標題：'.$job['title']."\n".
                    '任務連結：'.$job['link']."\n".
                    '雇主：'.$job['company']."\n".
                    '工作地方：'.$job['place']."\n".
                    '薪水：'.$job['salary']."\n",
                ];
            }

            if (! empty($sendNotifyText)) {
                $sendNotifyText .= "\n"."\n";
            }
        }

        if (! empty($this->jobTexts)) {
            foreach ($this->jobTexts as $index => $jobText) {
                $message = implode("\n", $jobText);
                $this->telegramService->sendMessage($this->chatId, $message);
                sleep(3);
            }
        }
    }
}
