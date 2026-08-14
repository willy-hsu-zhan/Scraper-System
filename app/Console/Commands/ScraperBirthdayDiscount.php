<?php

namespace App\Console\Commands;

use App\Services\Scraper\BirthdayDiscount\BirthdayDiscountScraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ScraperBirthdayDiscount extends Command
{
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

    protected $chatId = TelegramChat::BIRTHDAY_DISCOUNT;

    public function __construct(TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $scraper = new BirthdayDiscountScraper($this->seleniumService);
        $items = $scraper->run();

        if (! empty($items)) {
            foreach ($items as $item) {
                $this->telegramService->sendMessage($this->chatId, $scraper->format($item));
                sleep(7);
            }
        }

        $this->info('完成，共抓取 '.count($items).' 筆');
    }
}
