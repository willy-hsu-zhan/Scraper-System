<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use App\Services\LineNotify;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use Illuminate\Support\Facades\Log;

class ScraperOjt extends Command
{
    protected $signature = 'app:scraper-ojt';
    protected $description = '抓取台灣就業通產業人才投資方案課程資訊，並發送 Telegram 通知';

    protected $lineNotify;
    protected $telegramService;
    protected $seleniumService;
    protected $chatId = '-4968260856'; // Telegram 群組 ID

    public function __construct(LineNotify $lineNotify, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotify = $lineNotify;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $this->info("🚀 開始抓取台灣就業通課程資料...");

        try {
            $driver = $this->seleniumService->openWebDriver();

            $driver->get('https://ojt.wda.gov.tw/ClassSearch');
            $this->waitForElement($driver, 'body', 10);
            sleep(1);

            // 先等待 radio 按鈕存在 Form_PlanType_1 = 產業人才投資方案
            $this->waitForElement($driver, '#Form_PlanType_1', 10);

            // 再點擊該 radio
            $this->clickElement($driver, '#Form_PlanType_1');

            // 強制等待 3 秒 // 點擊 radio btn 時會重新渲染
            sleep(3);

            // 等待元素出現 (不限可見性)
            $this->waitForElement($driver, '#Form_CTID_SHOW_1', 10);

            // 勾選地區（value 分別為 1、2、4）
            $this->clickElement($driver, '#Form_CTID_SHOW_1'); // 台北市
            $this->clickElement($driver, '#Form_CTID_SHOW_3'); // 新北市
            $this->clickElement($driver, '#Form_CTID_SHOW_5'); // 基隆市

            sleep(1); // 等待操作穩定

            // 點擊「送出」按鈕
            $this->clickElement($driver, 'button.btn-orange[title="送出"]');

            // 等待結果表格出現
            $driver->wait(15)->until(function($driver) {
                $rows = $driver->findElements(WebDriverBy::cssSelector('table tbody tr'));
                return count($rows) > 1; // 等待至少兩筆資料出現
            });

            $rows = $driver->findElements(WebDriverBy::xpath('//table//tr[@valign="middle"]'));
            $data = [];

            foreach ($rows as $row) {
                $cols = $row->findElements(WebDriverBy::tagName('td'));
                if (count($cols) < 12) {
                    continue; // 過濾開頭幾個無用元素，影響foreach
                }

               // 找第二欄的「另開新視窗」連結
                $linkElement = $cols[1]->findElement(WebDriverBy::xpath('.//a[contains(text(), "另開新視窗")]'));
                $href = trim($linkElement->getAttribute('href'));
                $baseUrl = 'https://ojt.wda.gov.tw';

                // 判斷 href 是不是以 http 開頭（絕對網址），如果不是就加上 baseUrl
                if (strpos($href, 'http') !== 0) {
                    $trainingUnitUrl = $baseUrl . $href;
                } else {
                    $trainingUnitUrl = $href;
                }

                $courseName = trim($driver->executeScript('return arguments[0].innerText;', [$cols[1]]));
                $courseName = str_replace('●「另開新視窗」', '', $courseName);
                $courseName = trim($courseName);
                $courseCode = trim(strip_tags($driver->executeScript('return arguments[0].innerHTML;', [$cols[2]])));

                if ($courseCode === '') {
                    continue; // 無課程代碼跳過
                }

                $trainingCount = trim($driver->executeScript('return arguments[0].innerText;', [$cols[3]]));
                $trainingHours = trim($driver->executeScript('return arguments[0].innerText;', [$cols[4]]));
                $studentCost = trim($driver->executeScript('return arguments[0].innerText;', [$cols[5]]));
                $governmentCost = trim($driver->executeScript('return arguments[0].innerText;', [$cols[6]]));

                $trainingLocation = trim($driver->executeScript('return arguments[0].innerText;', [$cols[7]]));
                $trainingLocation = str_replace(["\n", "\r"], ' ', $trainingLocation);
                $trainingLocation = preg_replace('/\s+/', ' ', $trainingLocation); // 把多餘空白合併


                $registrationDate = trim($driver->executeScript('return arguments[0].innerText;', [$cols[8]]));
                $registrationDateParts = explode('|', $registrationDate);
                $registrationDate = trim($registrationDateParts[0]) . ' ~ ' . trim($registrationDateParts[1]);

                $enrollmentStatus = trim($driver->executeScript('return arguments[0].innerText;', [$cols[9]]));

                $scheduledPeriod = trim($driver->executeScript('return arguments[0].innerText;', [$cols[10]]));
                $scheduledPeriodParts = explode('|', $scheduledPeriod);
                $scheduledPeriod = trim($scheduledPeriodParts[0]) . ' ~ ' . trim($scheduledPeriodParts[1]);

                $paymentMethod = trim($driver->executeScript('return arguments[0].innerText;', [$cols[11]]));

                $data[] = [
                    'trainingUnitUrl' => $trainingUnitUrl,
                    'courseCode' => $courseCode,
                    'courseName' => $courseName,
                    'trainingCount' => $trainingCount,
                    'trainingHours' => $trainingHours,
                    'studentCost' => $studentCost,
                    'governmentCost' => $governmentCost,
                    'trainingLocation' => $trainingLocation,
                    'registrationDate' => $registrationDate,
                    'enrollmentStatus' => $enrollmentStatus,
                    'scheduledPeriod' => $scheduledPeriod,
                    'paymentMethod' => $paymentMethod,
                ];
            }

            // 發送 Telegram 訊息時包含 URL
            foreach ($data as $course) {
                $message = "【課程名稱】" . $course['courseName'] . "\n"
                         . "【課程代碼】" . $course['courseCode'] . "\n"
                         . "【訓練班別網址】" . $course['trainingUnitUrl'] . "\n"
                         . "【訓練人數】" . $course['trainingCount'] . "\n"
                         . "【訓練時數】" . $course['trainingHours'] . "\n"
                         . "【學員負擔】" . $course['studentCost'] . "\n"
                         . "【政府負擔】" . $course['governmentCost'] . "\n"
                         . "【訓練地點】" . $course['trainingLocation'] . "\n"
                         . "【報名日期】" . $course['registrationDate'] . "\n"
                         . "【招生狀態】" . $course['enrollmentStatus'] . "\n"
                         . "【預訂訓練期間】" . $course['scheduledPeriod'] . "\n"
                         . "【報名繳費方式】" . $course['paymentMethod'];

                $this->telegramService->sendMessage($this->chatId, $message);
                sleep(2);
            }

        $this->info("✅ 所有課程已發送至 Telegram 群組");
        } catch (\Exception $e) {
            $this->error("❌ 發生錯誤：" . $e->getMessage());
            Log::error('[ScraperOjt] 錯誤', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function clickElement(RemoteWebDriver $driver, string $cssSelector)
    {
        $this->waitForElement($driver, $cssSelector);
        $element = $driver->findElement(WebDriverBy::cssSelector($cssSelector));
        $element->click();
    }

    protected function waitForElement(RemoteWebDriver $driver, string $cssSelector, int $timeout = 10)
    {
        try {
            $wait = new \Facebook\WebDriver\WebDriverWait($driver, $timeout);
            return $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::cssSelector($cssSelector)
                )
            );
        } catch (\Exception $e) {
            Log::error("[waitForElement] 找不到元素：{$cssSelector}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
