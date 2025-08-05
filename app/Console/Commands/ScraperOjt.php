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
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverKeys;
use App\Models\GovOjt;
use Carbon\Carbon;

class ScraperOjt extends Command
{
    protected $signature = 'app:scraper-ojt {regionCode?}';
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

        // 取得英文參數 regionCode
        $regionCode = $this->argument('regionCode');

        // 定義英文縮寫對應的 CSS 選擇器
        $regions = [
            'TPE' => '#Form_CTID_SHOW_1', // 台北市
            'KEE' => '#Form_CTID_SHOW_2', // 基隆市
            'NWT' => '#Form_CTID_SHOW_3', // 新北市
        ];

        // 如果有帶參數，判斷是否存在於支援列表
        if ($regionCode) {
            if (!array_key_exists($regionCode, $regions)) {
                $this->error("Unsupported region code: {$regionCode}");
                return 1; // 直接結束
            }
            // 只保留指定的區域
            $regions = [
                $regionCode => $regions[$regionCode],
            ];
        }
        else {
            $this->error("請帶入參數 (TPE KEE NWT) ");
            return 1; // 直接結束
        }

        $allData = [];

        try {
            foreach ($regions as $regionName => $cssSelector) {
                // 開啟瀏覽器
                // 203.74.125.18:8888
                $proxy_ip = '59.124.62.9:3128';
                $driver = $this->seleniumService->openWebDriver(true, $proxy_ip);
                // $driver->manage()->deleteAllCookies();

                $driver->get('https://ojt.wda.gov.tw/ClassSearch');
                $this->waitForElement($driver, 'body', 10);
                sleep(5);

                // 點選「產業人才投資方案」radio
                $this->waitForElement($driver, '#Form_PlanType_1', 10);
                $this->clickElement($driver, '#Form_PlanType_1');
                sleep(5);

                $this->info("👉 開始抓取地區：{$regionName}");

                // 等待並點選地區選項
                $this->waitForElement($driver, $cssSelector, 10);
                $this->clickElement($driver, $cssSelector);
                sleep(5);

                // 送出查詢
                $this->clickElement($driver, 'button.btn-orange[title="送出"]');

                // 等待結果表格及分頁選項出現
                $this->waitForElement($driver, '#PagingInfo_PageSize', 10);

                // 按 ESC 鍵關閉可能的彈窗
                try {
                    $body = $driver->findElement(WebDriverBy::tagName('body'));
                    $actions = new WebDriverActions($driver);
                    $actions->sendKeys($body, WebDriverKeys::ESCAPE)->perform();
                } catch (\Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                    sleep(5);
                    $body = $driver->findElement(WebDriverBy::tagName('body'));
                    $actions = new WebDriverActions($driver);
                    $actions->sendKeys($body, WebDriverKeys::ESCAPE)->perform();
                }

                // 設定每頁顯示100筆
                $pageSizeSelect = $driver->findElement(WebDriverBy::cssSelector('#PagingInfo_PageSize'));
                $driver->executeScript("arguments[0].value = '100'; arguments[0].dispatchEvent(new Event('change'));", [$pageSizeSelect]);
                sleep(5);
                $this->clickElement($driver, '#btnGoRecCount');

                // 等待表格出現且有資料
                $driver->wait(15)->until(function($driver) {
                    $rows = $driver->findElements(WebDriverBy::cssSelector('table tbody tr'));
                    return count($rows) > 15;
                });

                // 抓取資料，重新取得每一列元素，避免 stale element
                $rows = $driver->findElements(WebDriverBy::xpath('//table//tr[@valign="middle"]'));
                $data = [];

                foreach ($rows as $row) {
                    try {
                        $cols = $row->findElements(WebDriverBy::tagName('td'));
                        if (count($cols) < 12) {
                            continue;
                        }

                        $linkElement = $cols[1]->findElement(WebDriverBy::xpath('.//a[contains(text(), "另開新視窗")]'));
                        $href = trim($linkElement->getAttribute('href'));
                        $baseUrl = 'https://ojt.wda.gov.tw';

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
                            continue;
                        }

                        $trainingCount = trim($driver->executeScript('return arguments[0].innerText;', [$cols[3]]));
                        $trainingHours = trim($driver->executeScript('return arguments[0].innerText;', [$cols[4]]));
                        $studentCost = trim($driver->executeScript('return arguments[0].innerText;', [$cols[5]]));
                        $governmentCost = trim($driver->executeScript('return arguments[0].innerText;', [$cols[6]]));
                        $trainingLocation = trim($driver->executeScript('return arguments[0].innerText;', [$cols[7]]));
                        $trainingLocation = str_replace(["\n", "\r"], ' ', $trainingLocation);
                        $trainingLocation = preg_replace('/\s+/', ' ', $trainingLocation);

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
                    } catch (\Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                        $this->warn("⚠️ 發生 stale element，該列資料跳過");
                        continue;
                    }
                }

                $allData = array_merge($allData, $data);

                $this->info("✅ 地區 {$regionName} 抓取完成，共 " . count($data) . " 筆資料");

                // 關閉瀏覽器
                $driver->quit();
                // 等待避免對伺服器壓力太大
                sleep(5);
            }

            // 發送 Telegram
            foreach ($allData as $course) {
                // 以 trainingUnitUrl 為唯一鍵查找
                $exists = GovOjt::where('training_unit_url', $course['trainingUnitUrl'])->exists();

                if ($exists) {
                    // 資料已存在，略過
                    $this->info("⚠️ 課程已存在，略過通知：{$course['courseName']}");
                    continue;
                }

                [$registrationStartAt, $registrationEndAt] = $this->convertTaiwanDatetimeRange($course['registrationDate']);
                [$scheduledStartDate, $scheduledEndDate] = $this->convertTaiwanDateRange($course['scheduledPeriod']);

                GovOjt::create([
                    'training_unit_url' => $course['trainingUnitUrl'],
                    'course_code' => $course['courseCode'],
                    'course_name' => $course['courseName'],
                    'training_count' => $course['trainingCount'],
                    'training_hours' => $course['trainingHours'],
                    'student_cost' => str_replace(',', '', $course['studentCost']),
                    'government_cost' => str_replace(',', '', $course['governmentCost']),
                    'training_location' => $course['trainingLocation'],
                    'registration_start_at' => $registrationStartAt,
                    'registration_end_at' => $registrationEndAt,
                    'enrollment_status' => $course['enrollmentStatus'],
                    'scheduled_start_date' => $scheduledStartDate,
                    'scheduled_end_date' => $scheduledEndDate,
                    'payment_method' => $course['paymentMethod'],
                ]);

                // 發送 Telegram 通知
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
                        . "【報名繳費方式】" . $course['paymentMethod'] . "\n"
                        . "【加入Google日曆提醒】 " . $this->generateGoogleCalendarLink($course);

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
            if (isset($driver)) {
                $driver->quit();
            }
        } finally {
            if ($driver) {
                $driver->quit(); // 無論成功或失敗都關
            }
            sleep(5);
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
    protected function convertTaiwanDatetimeRange($range) {
        [$startStr, $endStr] = explode(' ~ ', $range);

        return [
            $this->convertTaiwanDatetime($startStr),
            $this->convertTaiwanDatetime($endStr),
        ];
    }

    protected function convertTaiwanDateRange($range) {
        [$startStr, $endStr] = explode(' ~ ', $range);

        return [
            $this->convertTaiwanDate($startStr),
            $this->convertTaiwanDate($endStr),
        ];
    }

    protected function convertTaiwanDatetime($taiwanDatetime) {
        // 轉西元年
        $parts = explode(' ', $taiwanDatetime);
        [$y, $m, $d] = explode('/', $parts[0]);
        $hour = $parts[1] ?? '00:00:00';
        return (intval($y) + 1911) . "-$m-$d $hour";
    }

    protected function convertTaiwanDate($taiwanDate) {
        [$y, $m, $d] = explode('/', $taiwanDate);
        return (intval($y) + 1911) . "-$m-$d";
    }

    protected function toGoogleCalendarDateTime($dateString)
    {
        $dt = Carbon::createFromFormat('Y-m-d', $dateString, 'Asia/Taipei');
        return $dt->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
    }

    /**
     * 產生 Google 日曆事件新增連結
     */
    protected function generateGoogleCalendarLink(array $course): string
    {
        $title = urlencode($course['courseName']);
        $details = urlencode("報名網址：" . $course['trainingUnitUrl']);
        $location = urlencode($course['trainingLocation']);

        // 將報名開始與結束時間轉成 Google Calendar 格式
        [$start, $end] = $this->convertTaiwanDatetimeRange($course['registrationDate']);
        $startUTC = $this->toGoogleCalendarDateTime($start);
        $endUTC = $this->toGoogleCalendarDateTime($end);

        $dates = "{$startUTC}/{$endUTC}";

        $url = "https://calendar.google.com/calendar/render?action=TEMPLATE";
        $url .= "&text={$title}";
        $url .= "&dates={$dates}";
        $url .= "&details={$details}";
        $url .= "&location={$location}";
        $url .= "&sf=true&output=xml";

        return $url;
    }
}
