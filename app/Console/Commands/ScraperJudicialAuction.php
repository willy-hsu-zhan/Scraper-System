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

        try {
            $wait = new \Facebook\WebDriver\WebDriverWait($driver, 20, 500);

            // 1️⃣ 開頁
            $driver->get('https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02.htm');

            sleep(2); // 等 JS 初始化

            // 1. 進 iframe
            $driver->switchTo()->frame(
                $driver->findElement(WebDriverBy::id('v1'))
            );

            $driver->takeScreenshot(...);

            // 選法院：高雄地方法院
            $driver->findElement(WebDriverBy::id('court'))
                ->findElement(WebDriverBy::xpath("//option[contains(text(),'臺灣高雄地方法院')]"))
                ->click();

            // 拍賣標的：房屋或土地
            $driver->findElement(WebDriverBy::id('proptype_c51c52'))
                ->click();

            // 權利範圍：全部
            $driver->findElement(WebDriverBy::id('rrange_ALL'))
                ->click();

            // 排序：上架日期
            $driver->findElement(
                WebDriverBy::cssSelector('label[for="column_date"]')
            )->click();

            // 3. 查詢
            $driver->findElement(WebDriverBy::id('query_btn'))
                ->click();

            sleep(5); // 等 JS 初始化
            // 5️⃣ 抓資料
            $rows = $driver->findElements(
                \Facebook\WebDriver\WebDriverBy::cssSelector('table tbody tr')
            );

            // 📸 查詢結果畫面
            $screenshotPath = storage_path('logs/debug_after_search.png');
            $driver->takeScreenshot($screenshotPath);

            $this->info("查詢後截圖：{$screenshotPath}");

            sleep(5); // 等 JS 初始化

            //  切回主頁
            $driver->switchTo()->defaultContent();

            //  進結果 iframe
            $driver->switchTo()->frame(
                $driver->findElement(WebDriverBy::id('v2'))
            );

            sleep(2);

            // 抓資料
            $rows = $driver->findElements(
                WebDriverBy::cssSelector('tr[id="trdata"]')
            );

            $result = [];

            foreach ($rows as $row) {

                $cols = $row->findElements(WebDriverBy::cssSelector('td'));

                if (count($cols) < 7) {
                    continue;
                }

                // 法院
                $court = trim(str_replace("\n", '', $cols[1]->getText()));

                // 案號
                $caseNo = trim($cols[2]->getText());

                // 日期 + 拍次
                $date = trim($cols[3]->getText());

                // 地區
                $area = trim($cols[4]->getText());

                // 地址 + PDF link
                $linkEl = $cols[5]->findElement(WebDriverBy::cssSelector('a'));

                $addr = trim($linkEl->getText());

                // PDF link
                $pdfPath = $linkEl->getAttribute('data-filenm');

                $pdfUrl = null;
                if (!empty($pdfPath)) {
                    $pdfUrl = "https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02/DO_VIEWPDF.htm?filenm=" . $pdfPath;
                }

                // 價格
                $price = trim($cols[6]->getText());

                $result[] = [
                    'court'    => $court,
                    'case_no'  => $caseNo,
                    'date'     => $date,
                    'area'     => $area,
                    'addr'     => $addr,
                    'price'    => $price,
                    'pdf_url'  => $pdfUrl,
                ];
            }

            $this->info('完成，共 ' . count($result) . ' 筆');
            dd($result);

        } catch (\Exception $e) {
            $this->error($e->getMessage());

        } finally {
            $driver->quit();
        }
    }
}
