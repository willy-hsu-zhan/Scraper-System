<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;
use App\Models\FreeGame4Gamers;

class Scraper4GamersFreeGame extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:scraper-4gamers-free';

    /**
     * The console command description.
     */
    protected $description = 'Scrape 4Gamers 限時免費 news';

    protected $telegramService;
    protected $seleniumService;
    protected $jobTexts;
    protected $chatId = '-5107922211'; // 暫時先用全域變數 // 日後聊天室擴充再更改

    public function __construct(TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $webDriver = $this->seleniumService->openWebDriver();

        $webDriver->get('https://www.4gamers.com.tw/news/tag/限時免費');
        sleep(3);

        $items = $webDriver->findElements(
            WebDriverBy::cssSelector('section ul > li')
        );

        $results = [];

        foreach ($items as $item) {

            try {
                // 連結
                $a = $item->findElement(WebDriverBy::cssSelector('a'));
                $link = 'https://www.4gamers.com.tw' . $a->getAttribute('href');

                // 標題
                $title = trim(
                    $item->findElement(WebDriverBy::cssSelector('h3'))->getText()
                );

                // 簡介
                $desc = '';
                try {
                    $desc = trim(
                        $item->findElement(WebDriverBy::cssSelector('p'))->getText()
                    );
                } catch (\Exception $e) {}

                // 日期
                $date = null;
                try {
                    $time = $item->findElement(WebDriverBy::cssSelector('time'));
                    $date = $time->getAttribute('datetime');
                } catch (\Exception $e) {}

                $results[] = [
                    'title'      => $title,
                    'link'       => $link,
                    'description'=> $desc,
                    'date'       => $date,
                ];

            } catch (\Exception $e) {
                continue;
            }
        }

        $newItems = [];

        foreach ($results as $item) {
            $titleHash = FreeGame4Gamers::makeTitleHash($item['title']);
            $record = FreeGame4Gamers::where('title_hash', $titleHash)->first();

            if ($record) {
                // 已存在 → 更新內容（避免描述變動）
                $record->update([
                    'description'  => $item['description'],
                    'link'         => $item['link'],
                    'publish_date' => $item['date'],
                ]);
                continue;
            }

            // 新資料
            FreeGame4Gamers::create([
                'title'        => $item['title'],
                'title_hash'   => $titleHash,
                'description'  => $item['description'],
                'link'         => $item['link'],
                'publish_date' => $item['date'],
                'is_push'      => false,
            ]);

            $newItems[] = $item;
        }

        $this->pushNewFreeGames();
        $webDriver->quit();
    }

    private function pushNewFreeGames()
    {
        $items = FreeGame4Gamers::where('is_push', false)
            ->orderByDesc('publish_date')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        foreach ($items as $index => $game) {

            $text  = "🎮 4Gamers 限時免費\n\n";
            $text .= '🆓 ' . $game->title . "\n";

            if (!empty($game->description)) {
                $text .= '📝 ' . $game->description . "\n";
            }

            if (!empty($game->publish_date)) {
                $text .= '📅 發布日期：' . $game->publish_date . "\n";
            }

            $text .= "\n🔗 " . $game->link;

            $this->telegramService->sendMessage($this->chatId, $text);

            $game->update([
                'is_push' => true,
            ]);

            sleep(2);
        }
    }
}
