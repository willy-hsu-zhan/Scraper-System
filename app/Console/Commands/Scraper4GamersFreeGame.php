<?php

namespace App\Console\Commands;

use App\Services\Scraper\FreeGame\FreeGameScraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class Scraper4GamersFreeGame extends Command
{
    protected $signature = 'app:scraper-4gamers-free';

    protected $description = 'Scrape 4Gamers 限時免費 news';

    protected $telegramService;

    protected $seleniumService;
    protected $chatId = TelegramChat::FREE_GAME;

    public function __construct(TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $scraper = new FreeGameScraper($this->seleniumService);
        $newItems = $scraper->run();

        foreach ($newItems as $item) {
            $this->telegramService->sendMessage($this->chatId, $scraper->format($item));
            sleep(2);
        }

        $this->info('完成，共抓取 '.count($newItems).' 筆');
    }
}
