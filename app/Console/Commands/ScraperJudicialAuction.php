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

    private function scrapeCourt($driver, string $courtCode, string $courtName)
    {
        $driver->get('https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02.htm');

        sleep(2);

        /**
         * 進 iframe v1（查詢條件）
         */
        $driver->switchTo()->frame(
            $driver->findElement(WebDriverBy::id('v1'))
        );

        /**
         * 選法院
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
         * 切 iframe v2（結果）
         */
        $driver->switchTo()->defaultContent();

        $driver->switchTo()->frame(
            $driver->findElement(WebDriverBy::id('v2'))
        );

        sleep(2);

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
                 * 地址 + PDF 安全解析
                 */
                $address = trim($cols[5]->getText());
                $pdfUrl = null;

                $links = $cols[5]->findElements(WebDriverBy::cssSelector('a'));

                if (!empty($links)) {

                    $link = $links[0];

                    $text = trim($link->getText());

                    if (!empty($text)) {
                        $address = $text;
                    }

                    $pdfFile = $link->getAttribute('data-filenm');

                    if (!empty($pdfFile)) {
                        $pdfUrl =
                            "https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02/DO_VIEWPDF.htm?filenm={$pdfFile}";
                    }
                }

                $priceText = trim($cols[6]->getText());

                // 只保留數字
                $price = (int) preg_replace('/[^\d]/', '', $priceText);

                // 是否點交（新增）
                $isHandover = null;

                if (isset($cols[7])) {
                    $handoverText = trim($cols[7]->getText());

                    if (str_contains($handoverText, '點交')) {
                        $isHandover = '點交';
                    } else {
                        $isHandover = $handoverText; // 防止未來有「不點交」
                    }
                }

                $item = [
                    'court'        => $courtName,
                    'case_no'      => $caseNo,
                    'auction_date' => $auctionDate,
                    'area'         => $area,
                    'address'      => $address,
                    'price'        => $price,
                    'is_handover'  => $isHandover,
                    'pdf_url'      => $pdfUrl,
                ];

                $record = JudicialAuction::firstOrCreate(
                    [
                        'court'   => $courtName,
                        'case_no' => $caseNo,
                    ],
                    $item
                );

                /**
                 * 只通知新資料
                 */
                if ($record->wasRecentlyCreated) {

                    $message =
                        "🏠 新法拍案件\n\n" .

                        "🏛 法院\n{$record->court}\n\n" .
                        "📌 案號\n{$record->case_no}\n" .
                        "{$record->auction_date}\n\n" .

                        "📍 地區\n{$record->area}\n\n" .

                        "🏡 地址\n{$record->address}\n\n" .

                        "💰 價格\n{$record->price}\n\n" .

                        "📄 PDF\n{$record->pdf_url}";

                    $this->telegramService->sendMessage(
                        $this->chatId,
                        $message
                    );

                    sleep(2);
                }
            } catch (\Throwable $e) {
                // 單筆錯誤不影響整批
                continue;
            }
        }
    }
}
