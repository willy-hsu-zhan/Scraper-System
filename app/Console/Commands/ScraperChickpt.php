<?php

namespace App\Console\Commands;

use App\Services\LineNotifyService;
use App\Services\Scraper\Chickpt\ChickptScraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Exception;
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
}
