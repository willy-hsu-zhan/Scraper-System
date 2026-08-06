<?php

namespace App\Console\Commands;

use App\Models\JudicialAuction;
use App\Services\GeminiService;
use App\Services\SeleniumService;
use App\Services\TelegramService;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScraperJudicialAuction extends Command
{
    protected $signature = 'app:scraper-judicial-auction';

    protected $description = 'Stable judicial auction scraper (multi-court safe version)';

    protected $telegramService;

    protected $seleniumService;

    protected $geminiService;

    protected $chatId = '-5434110194';

    public function __construct(
        TelegramService $telegramService,
        SeleniumService $seleniumService,
        GeminiService $geminiService
    ) {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
        $this->geminiService = $geminiService;
    }

    public function handle()
    {
        $driver = $this->seleniumService->openWebDriver();

        try {

            $courts = [
                // 'TPD' => '臺灣臺北地方法院',
                // 'PCD' => '臺灣新北地方法院',
                // 'SLD' => '臺灣士林地方法院',
                // 'TYD' => '臺灣桃園地方法院',
                // 'SCD' => '臺灣新竹地方法院',
                // 'MLD' => '臺灣苗栗地方法院',
                // 'TCD' => '臺灣臺中地方法院',
                // 'NTD' => '臺灣南投地方法院',
                // 'CHD' => '臺灣彰化地方法院',
                // 'ULD' => '臺灣雲林地方法院',
                // 'CYD' => '臺灣嘉義地方法院',
                // 'TND' => '臺灣臺南地方法院',
                // 'CTD' => '臺灣橋頭地方法院',
                // 'KSD' => '臺灣高雄地方法院',
                // 'PTD' => '臺灣屏東地方法院',
                // 'TTD' => '臺灣臺東地方法院',
                // 'HLD' => '臺灣花蓮地方法院',
                // 'ILD' => '臺灣宜蘭地方法院',
                'KLD' => '臺灣基隆地方法院',
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

        // v1 iframe
        $driver->switchTo()->frame(
            $driver->findElement(WebDriverBy::id('v1'))
        );

        // 選法院
        $courtOptions = $driver->findElements(WebDriverBy::cssSelector('#court option'));

        foreach ($courtOptions as $option) {
            if (str_contains($option->getAttribute('value'), $courtCode)) {
                $option->click();
                break;
            }
        }

        // 查詢條件
        $driver->findElement(WebDriverBy::id('proptype_c51c52'))->click();
        $driver->findElement(WebDriverBy::id('rrange_ALL'))->click();
        $driver->findElement(WebDriverBy::cssSelector('label[for="column_date"]'))->click();

        // 查詢
        $driver->findElement(WebDriverBy::id('query_btn'))->click();

        sleep(5);

        // v2 iframe
        $driver->switchTo()->defaultContent();
        $driver->switchTo()->frame(
            $driver->findElement(WebDriverBy::id('v2'))
        );

        sleep(2);

        $rows = $driver->findElements(WebDriverBy::cssSelector('tr[id="trdata"]'));

        $this->info("   ➜ {$courtName} 共 ".count($rows).' 筆');

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
                 * 地址 + PDF
                 */
                $address = trim($cols[5]->getText());
                $pdfUrl = null;
                $pdfSummary = null;

                $links = $cols[5]->findElements(WebDriverBy::cssSelector('a'));

                if (! empty($links)) {

                    $link = $links[0];

                    $text = trim($link->getText());

                    if (! empty($text)) {
                        $address = $text;
                    }

                    $pdfFile = $link->getAttribute('data-filenm');

                    if (! empty($pdfFile)) {
                        $pdfUrl = "https://aomp109.judicial.gov.tw/judbp/wkw/WHD1A02/DO_VIEWPDF.htm?filenm={$pdfFile}";
                    }
                }

                /**
                 * 價格
                 */
                $priceRaw = trim($cols[6]->getText());
                $priceNumber = (int) preg_replace('/[^\d]/', '', $priceRaw);
                $priceText = $this->formatPriceToChinese($priceNumber);

                /**
                 * 點交（最後欄）
                 */
                $isHandover = null;

                if (isset($cols[7])) {
                    $handoverText = trim($cols[7]->getText());

                    if (str_contains($handoverText, '不點交')) {
                        $isHandover = '不點交';
                    } elseif (str_contains($handoverText, '點交')) {
                        $isHandover = '點交';
                    } else {
                        $isHandover = $handoverText ?: null;
                    }
                }

                /**
                 * DB
                 */
                $item = [
                    'court' => $courtName,
                    'case_no' => $caseNo,
                    'auction_date' => $auctionDate,
                    'area' => $area,
                    'address' => $address,
                    'price' => $priceNumber,
                    'is_handover' => $isHandover,
                    'pdf_url' => $pdfUrl,
                ];

                $judicalAction = JudicialAuction::firstOrCreate(
                    [
                        'court' => $courtName,
                        'case_no' => $caseNo,
                    ],
                    $item
                );

                if ($judicalAction->wasRecentlyCreated) {

                    /**
                     * 🧾 Telegram message
                     */
                    $message =
                        "🏠 新法拍案件\n\n".
                        "🏛 法院：{$judicalAction->court}\n".
                        "📌 案號：{$judicalAction->case_no}\n".
                        "📅 拍賣日：{$judicalAction->auction_date}\n\n".
                        "📍 區域：{$judicalAction->area}\n\n".
                        "🏡 地址：{$judicalAction->address}\n\n".
                        "💰 價格：{$priceText}（{$priceNumber}元）\n\n".
                        '📦 點交：'.($isHandover ?? '未標示')."\n\n".
                        "📄 PDF：{$judicalAction->pdf_url}";

                    /**
                     * ⚠️ 防止 Telegram 超長
                     */
                    if (strlen($message) > 3800) {
                        $message = mb_substr($message, 0, 3800)."\n\n...(略)";
                    }

                    // BTN 產生

                    // AI 摘要
                    try {
                        $this->info("🤖 正在分析：{$judicalAction->course_name}");

                        $summary = $this->geminiService->summarizePdfByUrl(
                            $judicalAction->pdf_url
                        );

                        if (! empty($summary)) {
                            $judicalAction->update([
                                'ai_summary' => $summary,
                            ]);

                            $this->info('✅ AI 摘要完成');
                        } else {
                            $this->warn('⚠️ AI 沒有回傳摘要');
                        }

                    } catch (\Throwable $e) {
                        Log::error('AI 摘要失敗', [
                            'error' => $e->getMessage(),
                        ]);

                        $this->warn('❌ AI 分析失敗：'.$e->getMessage());
                    }

                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => '產生 AI 摘要',
                                    'callback_data' => "auction_summary:{$judicalAction->id}",
                                ],
                            ],
                        ],
                    ];

                    $this->telegramService->sendMessage(
                        $this->chatId,
                        $message,
                        $keyboard
                    );

                    sleep(2);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    private function formatPriceToChinese(int $number): string
    {
        if ($number >= 10000) {
            $wan = intval($number / 10000);
            $rest = $number % 10000;

            return $rest > 0
                ? "{$wan}萬{$rest}元"
                : "{$wan}萬元";
        }

        return "{$number}元";
    }
}
