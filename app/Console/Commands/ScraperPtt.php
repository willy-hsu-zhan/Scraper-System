<?php

namespace App\Console\Commands;

use App\Models\JobPtt;
use App\Services\LineNotifyService;
use App\Services\Scraper\Ptt\PttScraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Carbon\Carbon;
use Exception;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

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

    protected $chatId = TelegramChat::PTT_GROUP_CHAT_ID;

    protected $telegramService;

    protected $seleniumService;

    protected $jobTexts;

    public function __construct(LineNotifyService $lineNotifyService, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotifyService = $lineNotifyService;
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
            return '時薪 '.$match[1];
        }

        // 月薪
        if (preg_match('/月薪\s?(\d+)/u', $title, $match)) {
            return '月薪 '.$match[1];
        }

        // 日薪
        if (preg_match('/日薪\s?(\d+)/u', $title, $match)) {
            return '日薪 '.$match[1];
        }

        // 薪資數字，例如 200/hr、500元
        if (preg_match('/(\d+)\s?(元|\/hr|\/小時|\/時)/u', $title, $match)) {
            return $match[1].'元';
        }

        if (mb_strpos($title, '面議') !== false) {
            return '面議';
        }

        return null;
    }

    public function handle()
    {
        $scraper = new PttScraper($this->seleniumService);
        $newJobs = $scraper->run();

        if (! empty($newJobs)) {
            foreach ($newJobs as $job) {
                $this->telegramService->sendMessage($this->chatId, $scraper->format($job));
                sleep(3);
            }

            $this->telegramService->sendMessage(
                $this->chatId,
                '最後更新時間：'.Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s')
            );
        }

        $this->info('完成，共抓取 '.count($newJobs).' 筆');
    }

    private function shouldSkipArticle(string $title): bool
    {
        $skipKeywords = [
            '[公告]',
            'Fw:',
            'FW:',
            'Re:',
            '板規',
            '置底',
            '版主',
            '問答',
        ];

        foreach ($skipKeywords as $keyword) {

            if (mb_strpos($title, $keyword) !== false) {

                return true;
            }
        }

        return false;
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
        if (! empty($this->jobTexts)) {
            foreach ($this->jobTexts as $message) {
                $this->telegramService->sendMessage($this->chatId, $message);
                sleep(3);
            }

            $this->telegramService->sendMessage($this->chatId, '最後更新時間：'.Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s'));
        }
    }

    public function sendPeriodNoJobMsg()
    {
        $errorMessage = "📢 **PTT 打工板** 📢\n\n";
        $errorMessage .= '最近更新時間：'.
            Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s').
            ' ~ '.
            Carbon::now()->subMinutes(30)->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s')."\n";
        $errorMessage .= '沒有新的任務！！'."\n";
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
}
