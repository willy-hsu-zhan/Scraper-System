<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JudicialAuction;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;

class ScraperJudicialAuction extends Command
{
    protected $signature = 'app:scraper-judicial-auction';
    protected $description = 'Stable judicial auction scraper (multi-court safe version)';

    protected $telegramService;
    protected $seleniumService;
    protected $chatId = '-5434110194';

    public function __construct(
        TelegramService $telegramService,
        SeleniumService $seleniumService
    ) {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $driver = $this->seleniumService->openWebDriver();

        try {

            /**
             * 🔥 分法院（避免全部 timeout）
             */
            $courts = [
                'KSD' => '臺灣高雄地方法院',
                'TCD' => '臺灣臺中地方法院',
                'TPD' => '臺灣臺北地方法院',
                'PCD' => '臺灣新北地方法院',
                'TND' => '臺灣臺南地方法院',
            ];

            foreach ($courts as $code => $name) {

                $this->info("📍 抓取法院：{$name}");

                $this->scrapeCourt($driver, $code, $name);

                sleep(2);
            }

        } catch (\Throwable $e) {
            $this->error($e->getMessage());

        } finally {
            $driver->quit();
        }
    }

    /**
     * =========================
     * 單一法院抓取核心
     * =========================
     */
    private function scrapeCourt($driver, string $courtCode, string $courtName)
    {
        $driver->get('https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02.htm');

        sleep(2);

        /**
         * iframe v1（查詢條件）
         */
        $driver->switchTo()->frame(
            $driver->findElement(WebDriverBy::id('v1'))
        );

        /**
         * 選法院（safe）
         */
        $courtOptions = $driver->findElements(
            WebDriverBy::cssSelector("#court option")
        );

        foreach ($courtOptions as $option) {
            if (str_contains($option->getAttribute('value'), $courtCode)) {
                $option->click();
                break;
            }
        }

        /**
         * 查詢條件
         */
        $driver->findElement(WebDriverBy::id('proptype_c51c52'))->click();
        $driver->findElement(WebDriverBy::id('rrange_ALL'))->click();
        $driver->findElement(WebDriverBy::cssSelector('label[for="column_date"]'))->click();

        /**
         * 查詢
         */
        $driver->findElement(WebDriverBy::id('query_btn'))->click();

        sleep(5);

        /**
         * 切結果 iframe
         */
        $driver->switchTo()->defaultContent();

        $driver->switchTo()->frame(
            $driver->findElement(WebDriverBy::id('v2'))
        );

        sleep(2);

        /**
         * 抓資料列
         */
        $rows = $driver->findElements(
            WebDriverBy::cssSelector('tr[id="trdata"]')
        );

        $this->info("   ➜ {$courtName} 共 " . count($rows) . " 筆");

        foreach ($rows as $row) {

            try {

                $cols = $row->findElements(WebDriverBy::cssSelector('td'));

                if (count($cols) < 7) {
                    continue;
                }

                $caseNo = trim($cols[2]->getText());
                $auctionDate = trim($cols[3]->getText());
                $area = trim($cols[4]->getText());

                /**
                 * =========================
                 * ⚠️ 安全解析 address + pdf
                 * =========================
                 */
                $address = trim($cols[5]->getText());

                $pdfUrl = null;

                $links = $cols[5]->findElements(WebDriverBy::cssSelector('a'));

                if (count($links) > 0) {

                    $link = $links[0];

                    $address = trim($link->getText()) ?: $address;

                    $pdfFile = $link->getAttribute('data-filenm');

                    if (!empty($pdfFile)) {
                        $pdfUrl = "https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02/DO_VIEWPDF.htm?filenm={$pdfFile}";
                    }
                }

                $price = trim($cols[6]->getText());

                /**
                 * DB item
                 */
                $item = [
                    'court'        => $courtName,
                    'case_no'      => $caseNo,
                    'auction_date' => $auctionDate,
                    'area'         => $area,
                    'address'      => $address,
                    'price'        => $price,
                    'pdf_url'      => $pdfUrl,
                ];

                /**
                 * =========================
                 * DB + notify only new
                 * =========================
                 */
                $record = JudicialAuction::firstOrCreate(
                    [
                        'court'   => $courtName,
                        'case_no' => $caseNo,
                    ],
                    $item
                );

                if ($record->wasRecentlyCreated) {

                    $this->telegramService->sendMessage(
                        $this->chatId,
                        "🏠 新法拍案件\n\n" .
                        "法院：{$record->court}\n" .
                        "案號：{$record->case_no}\n" .
                        "日期：{$record->auction_date}\n" .
                        "地區：{$record->area}\n" .
                        "地址：{$record->address}\n" .
                        "價格：{$record->price}\n" .
                        ($record->pdf_url ?? '')
                    );

                    sleep(2);
                }

            } catch (\Throwable $e) {
                // 🔥 單 row fail 不影響整批
                continue;
            }
        }
    }
}
