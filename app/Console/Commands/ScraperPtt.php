<?php

namespace App\Console\Commands;

use App\Services\Scraper\Ptt\PttScraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ScraperPtt extends Command
{
    protected $signature = 'app:scraper-ptt';

    protected $description = 'Scrape PTT part-time job listings';

    protected $chatId = TelegramChat::PTT;

    public function __construct(
        protected TelegramService $telegramService,
        protected SeleniumService $seleniumService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $scraper = new PttScraper($this->seleniumService);
        $newJobs = $scraper->run();

        foreach ($newJobs as $job) {
            $this->telegramService->sendMessage(
                $this->chatId,
                $scraper->format($job)
            );

            sleep(3);
        }

        if (! empty($newJobs)) {
            $this->telegramService->sendMessage(
                $this->chatId,
                '最後更新時間：'.Carbon::now('Asia/Taipei')->format('Y-m-d H:i:s')
            );
        }

        $this->info('完成，共抓取 '.count($newJobs).' 筆');
    }
}
