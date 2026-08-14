<?php

namespace App\Console\Commands;

use App\Services\Scraper\Rent591\Rent591Scraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ScraperRent591 extends Command
{
    protected $signature = 'app:scraper-rent591';

    protected $description = 'Collect rental listings from 591.com.tw';

    protected $telegramService;

    protected $seleniumService;
    protected $chatId = TelegramChat::RENT591;

    public function __construct(TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();

        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $scraper = new Rent591Scraper($this->seleniumService);
        $newRents = $scraper->run();

        if (! empty($newRents)) {
            foreach ($newRents as $rent) {
                $this->telegramService->sendMessage($this->chatId, $scraper->format($rent));
                sleep(1);
            }
        }

        $this->info('完成，共抓取 '.count($newRents).' 筆資料');
    }
}
