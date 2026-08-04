<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use Illuminate\Support\Facades\Log;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverKeys;
use App\Models\GovOjt;
use Carbon\Carbon;
use App\Services\ReurlService;
use App\Services\GeminiService;
use Longman\TelegramBot\Entities\InlineKeyboard;

class ScraperOjt extends Command
{
    protected $signature = 'app:scraper-ojt';
    protected $description = '抓取台灣就業通產業人才投資方案課程資訊，並發送 Telegram 通知';

    protected $lineNotifyService;
    protected $telegramService;
    protected $seleniumService;
    protected $geminiService;
    protected $reurlService;
    protected $chatId = '-5185362500'; // Telegram 群組 ID

    public function __construct(TelegramService $telegramService, SeleniumService $seleniumService, GeminiService $geminiService, ReurlService $reurlService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
        $this->geminiService = $geminiService;
        $this->reurlService = $reurlService;
    }

    public function handle()
    {
        $this->info("🚀 開始抓取台灣就業通課程資料...");


        // 定義英文縮寫對應的 CSS 選擇器
        $regions = [
            'TPE' => '#Form_CTID_SHOW_1', // 台北市
            'KEE' => '#Form_CTID_SHOW_2', // 基隆市
            'NWT' => '#Form_CTID_SHOW_3', // 新北市
        ];


        $allData = [];

        try {
                // 開啟瀏覽器
                $driver = $this->seleniumService->openWebDriver();
                // $driver->manage()->deleteAllCookies();

                $driver->get('https://ojt.wda.gov.tw/ClassSearch');
                $this->waitForElement($driver, 'body', 10);
                sleep(5);

                // 點選「產業人才投資方案」radio
                $this->waitForElement($driver, '#Form_PlanType_1', 10);
                $this->clickElement($driver, '#Form_PlanType_1');
                sleep(5);

                $this->info("👉 開始抓取地區：{'TPE','KEE','NWT'}");

                // 等待並點選地區選項 // 台北 新北 基隆
                $this->waitForElement($driver, '#Form_CTID_SHOW_1', 10);
                $this->clickElement($driver, '#Form_CTID_SHOW_1');
                $this->clickElement($driver, '#Form_CTID_SHOW_2');
                $this->clickElement($driver, '#Form_CTID_SHOW_3');
                sleep(5);

                // 送出查詢
                $this->clickElement($driver, 'button.btn-orange[title="送出"]');

                // 等待結果表格及分頁選項出現
                //$this->waitForElement($driver, '#PagingInfo_PageSize', 10);
                $this->waitForTableHasRows($driver, 30);
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

                $this->waitForTableHasRows($driver, 20);

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
                        // 重抓 row
                        try {
                            $row = $driver->findElements(WebDriverBy::xpath('//table//tr[@valign="middle"]'))[$index];
                            $cols = $row->findElements(WebDriverBy::tagName('td'));
                            // 重試原本的操作
                        } catch (\Facebook\WebDriver\Exception\StaleElementReferenceException $e2) {
                            $this->warn("⚠️ 再次 stale element，該列資料跳過");
                            continue;
                        }
                    }
                }

                $allData = array_merge($allData, $data);

                $this->info("✅ 抓取完成，共 " . count($data) . " 筆資料");

                // 關閉瀏覽器
                $driver->quit();
                // 等待避免對伺服器壓力太大
                sleep(5);

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

                $govOjt = GovOjt::create([
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

                // AI 摘要
                try {
                    $this->info("🤖 正在分析：{$govOjt->course_name}");

                    $summary = $this->geminiService->summarizeCourseByUrl(
                        $govOjt->training_unit_url
                    );

                    if (!empty($summary)) {
                        $govOjt->update([
                            'ai_summary' => $summary,
                        ]);

                        $this->info("✅ AI 摘要完成");
                    } else {
                        $this->warn("⚠️ AI 沒有回傳摘要");
                    }

                } catch (\Throwable $e) {
                    Log::error('AI 摘要失敗', [
                        'course' => $govOjt->course_name,
                        'url' => $govOjt->training_unit_url,
                        'error' => $e->getMessage(),
                    ]);

                    $this->warn("❌ AI 分析失敗：" . $e->getMessage());
                }

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
                        . "【報名繳費方式】" . $course['paymentMethod'] . "";
                        //. "【加入Google日曆提醒】 " . $this->generateGoogleCalendarLink($course);

               $keyboard = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '🧠 AI 課程分析',
                                'callback_data' => "ojt_summary:{$govOjt->id}"
                            ]
                        ]
                    ]
                ];
                $this->telegramService->sendMessage($this->chatId, $message, $keyboard);
                sleep(3);
            }

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
        // 同步localDB to prod
        //$this->runSyncShell();
    }

    protected function clickElement(RemoteWebDriver $driver, string $cssSelector)
    {
        $this->waitForElement($driver, $cssSelector);
        $element = $driver->findElement(WebDriverBy::cssSelector($cssSelector));
        $element->click();
    }

    protected function waitForTableHasRows(RemoteWebDriver $driver, int $timeout = 30)
    {
        $driver->wait($timeout)->until(function ($driver) {
            $rows = $driver->findElements(
                WebDriverBy::cssSelector('table tbody tr')
            );
            return count($rows) > 0;
        });
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
        $dateString = trim($dateString);

        $format = str_contains($dateString, ':')
            ? 'Y-m-d H:i:s'
            : 'Y-m-d';

        $dt = Carbon::createFromFormat($format, $dateString, 'Asia/Taipei');

        return $dt->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
    }

    /**
     * 產生 Google 日曆事件新增連結
     */
    protected function generateGoogleCalendarLink(GovOjt $course): string
    {
        $title = urlencode($course->course_name);
        $details = urlencode("報名網址：" . $course->training_unit_url);
        $location = urlencode($course->training_location ?? '');

        // 報名開始時間（台灣）
        $start = Carbon::parse($course->registration_start_at, 'Asia/Taipei');

        // Google Calendar 必須有結束時間
        $end = $start->copy()->addMinutes(5);

        $startUTC = $start->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $endUTC = $end->copy()->setTimezone('UTC')->format('Ymd\THis\Z');

        $dates = "{$startUTC}/{$endUTC}";

        $url = "https://calendar.google.com/calendar/render?action=TEMPLATE";
        $url .= "&text={$title}";
        $url .= "&dates={$dates}";
        $url .= "&details={$details}";
        $url .= "&location={$location}";
        $url .= "&reminder=popup:60";
        $url .= "&sf=true&output=xml";


        // 轉短網址
        return $this->reurlService->shorten(
            $url,
            [
                'utm_source' => 'LINE',
                'utm_medium' => 'notification',
                'utm_campaign' => 'gov_course_calendar',
            ]
        ) ?? $url;
    }
}
