<?php

namespace App\Console\Commands;

use App\Services\LineNotifyService;
use App\Services\ProxyService;
use App\Services\Scraper\Dcard\DcardScraper;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ScraperDcard extends Command
{
    public $jobs = [];

    protected $lineNotifyService;

    protected $telegramService;

    protected $proxyService;

    protected $jobTexts;

    protected $chatId = TelegramChat::DCARD;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-dcard';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(LineNotifyService $lineNotifyService, TelegramService $telegramService, ProxyService $proxyService)
    {
        parent::__construct();
        $this->lineNotifyService = $lineNotifyService;
        $this->telegramService = $telegramService;
        $this->proxyService = $proxyService;
    }

    public function handle()
    {
        $scraper = new DcardScraper($this->proxyService);
        $items = $scraper->run();

        if (! empty($items)) {
            foreach ($items as $item) {
                $this->telegramService->sendMessage($this->chatId, $scraper->format($item));
                sleep(3);
            }
        }

        $this->info('完成，共抓取 '.count($items).' 筆');
    }
}
