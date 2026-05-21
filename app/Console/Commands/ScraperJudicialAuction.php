<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Rent591;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use App\Services\LineNotifyService;
use Illuminate\Support\Facades\File;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Support\Facades\Http;
use Facebook\WebDriver\WebDriverSelect;


class ScraperJudicialAuction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-judicial-auction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public $rents = [];
    protected $lineNotifyService;
    protected $telegramService;
    protected $seleniumService;
    protected $rentTexts;
    protected $chatId = '-4675025353';

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
        $driver = $this->seleniumService->openWebDriver();
        $wait = new WebDriverWait($driver, 30);

        $driver->get('https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02.htm');

        $wait = new WebDriverWait($driver, 30);

        //  等 iframe 出現
        $wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::id('v1')
            )
        );

        // 切 frame（不要管 src）
        $v1 = $driver->findElement(WebDriverBy::id('v1'));
        $driver->switchTo()->frame($v1);

        // 等真正 UI（唯一可靠）
        $wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::id('court')
            )
        );

         // 取得 frame 內 HTML（關鍵）
        $html = $driver->executeScript("return document.documentElement.outerHTML;");

        // 存檔
        File::put(
            storage_path('app/debug_v1.html'),
            $html
        );

        dd('saved');
    }
}
