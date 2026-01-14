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
use App\Services\LineNotify;
use Illuminate\Support\Facades\File;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Support\Facades\Http;

class ScraperRent591 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-rent591';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public $rents = [];
    protected $lineNotify;
    protected $telegramService;
    protected $seleniumService;
    protected $rentTexts;
    protected $chatId = '-4675025353'; // 暫時先用全域變數 // 日後聊天室擴充再更改 Hsu & Issac
    protected $chatId2 = '-5141434603'; // 暫時先用全域變數 // 日後聊天室擴充再更改 Hsu & Hsu

    public function __construct(LineNotify $lineNotify, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotify      = $lineNotify;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    /**
     * Execute the console command.
     */

     public function handle()
     {
         $regions = [
            //  ['name' => '台北市', 'region' => 1],
            //  ['name' => '基隆市', 'region' => 2],
            ['name' => '新北市', 'region' => 3],
            //  ['name' => '新竹市', 'region' => 4],
            //  ['name' => '新竹縣', 'region' => 5],
            //  ['name' => '桃園市', 'region' => 6],
            ['name' => '台中市', 'region' => 8],
            //  ['name' => '台南市', 'region' => 15],
            //  ['name' => '高雄市', 'region' => 17],
         ];

         $baseUrl = 'https://rent.591.com.tw/list?kind=2&sort=money_asc&price=0_5000';

         $webDriver = $this->seleniumService->openWebDriver();

         $newRents = [];

         foreach ($regions as $regionData) {
             $regionName = $regionData['name'];
             $regionCode = $regionData['region'];
             $url = $baseUrl . '&region=' . $regionCode;

             $webDriver->get($url);

             $wait = new WebDriverWait($webDriver, 3);
             try {
                 $wait->until(
                     WebDriverExpectedCondition::presenceOfElementLocated(
                         WebDriverBy::cssSelector('.item-info')
                     )
                 );
             } catch (\Exception $e) {
                 Log::info( "頁面加載失敗：{$e->getMessage()}\n");
                 continue;
             }

             $rentElements = $webDriver->findElements(WebDriverBy::cssSelector('.item-info'));

             foreach ($rentElements as $rentNode) {
                 try {
                     $title = $rentNode->findElement(WebDriverBy::cssSelector('.item-info-title a'))->getAttribute('title');
                     $link  = $rentNode->findElement(WebDriverBy::cssSelector('.item-info-title a'))->getAttribute('href');
                     $priceText = $rentNode
                         ->findElement(
                             WebDriverBy::cssSelector('.item-info-price .inline-flex-row')
                         )
                         ->getText();
                     $price = (int) str_replace(',', '', $priceText);


                     $exists = Rent591::where('link', $link)->exists();

                     if (!$exists) {
                         Rent591::create([
                             'title'      => $title,
                             'region'     => $regionName,
                             'price_info' => $price,
                             'link'       => $link,
                         ]);

                         $newRents[] = [
                             'title'     => $title,
                             'region'    => $regionName,
                             'price'     => $price,
                             'link'      => $link,
                         ];

                         Log::info( "✅ 新增一筆租屋資訊\n");
                     } else {
                         Log::info( "⚠️ 已存在，略過\n");
                     }

                     Log::info( "============================\n");
                     Log::info( "城市：$regionName\n");
                     Log::info( "標題：$title\n");
                     Log::info( "連結：$link\n");
                     Log::info( "價格：$price /月\n");

                 } catch (\Exception $e) {
                     Log::info( "跳過一筆($regionName):{$e->getMessage()}\n");
                 }
             }
         }

         $webDriver->quit();

         // 根據有無新資料發送 Telegram 通知
         if (count($newRents) > 0) {
             $this->sendRentNotifications($newRents);
         } else {
             //$this->sendPeriodNoRentMsg();
         }
     }

    public function sendRentNotifications($rents)
    {
        $chatId2 = '-5141434603';
        foreach ($rents as $rent) {
            $sendNotifyText  = "**** 591 租屋新資訊 ****\n\n";
            $sendNotifyText .= '城市：' . $rent['region'] . "\n";
            $sendNotifyText .= '標題：' . $rent['title'] . "\n";
            $sendNotifyText .= '租屋連結：' . $rent['link'] . "\n";
            $sendNotifyText .= '價格：' . $rent['price'] . " 元/月" . "\n";
            //$sendNotifyText .= '條件標籤：' . implode('、', $rent['tagTexts']) . "\n";
            //$sendNotifyText .= '聯絡資訊：' . $rent['ownerInfo'] . "\n\n";

            $this->telegramService->sendMessage($this->chatId, $sendNotifyText);
            sleep(1);
            $this->telegramService->sendMessage($this->chatId2, $sendNotifyText);
            sleep(3); // 避免過快推送被 Telegram 封鎖
        }

        //$this->sendFachaiGIF(); // 額外推送圖片

        $infoText = '最後更新時間：' . Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s');
        $this->telegramService->sendMessage($this->chatId, $infoText);
    }

    public function sendPeriodNoRentMsg()
    {
        $errorMessage  = "🚨 **591 租屋通知** 🚨\n\n";
        $errorMessage .= "時間：" .
            Carbon::now()->subHours(6)->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s') .
            ' ~ ' .
            Carbon::now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i:s') . "\n";
        $errorMessage .= "目前沒有新的租屋資訊喔！🥲";

        $this->telegramService->sendMessage($this->chatId, $errorMessage);
        sleep(1);
        $this->telegramService->sendMessage($this->chatId2, $errorMessage);
    }

}
