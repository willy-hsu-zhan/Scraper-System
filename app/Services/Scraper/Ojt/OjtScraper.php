<?php

namespace App\Services\Scraper\Ojt;

use App\Models\GovOjt;
use App\Services\GeminiService;
use App\Services\ReurlService;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use App\Services\TelegramService;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;

class OjtScraper extends BaseScraper
{
    protected SeleniumService $seleniumService;
    protected GeminiService $geminiService;
    protected ReurlService $reurlService;
    protected TelegramService $telegramService;
    protected OjtParser $parser;
    protected OjtFilter $filter;
    protected OjtFormatter $formatter;

    public function __construct(
        SeleniumService $seleniumService,
        GeminiService $geminiService,
        ReurlService $reurlService,
        TelegramService $telegramService
    ) {
        $this->seleniumService = $seleniumService;
        $this->geminiService = $geminiService;
        $this->reurlService = $reurlService;
        $this->telegramService = $telegramService;
        $this->parser = new OjtParser();
        $this->filter = new OjtFilter();
        $this->formatter = new OjtFormatter();
    }

    public function scrape(): array
    {
        $url = 'https://ojt.wda.gov.tw/ClassSearch';
        $driver = $this->seleniumService->openWebDriver();
        $items = [];

        try {
            $driver->get($url);
            $this->waitForElement($driver, 'body', 15);
            sleep(5);

            $this->waitForElement($driver, '#Form_PlanType_1', 15);
            $this->clickElement($driver, '#Form_PlanType_1');
            sleep(5);

            $this->waitForElement($driver, '#Form_CTID_SHOW_1', 15);
            $this->clickElement($driver, '#Form_CTID_SHOW_1');
            $this->clickElement($driver, '#Form_CTID_SHOW_2');
            $this->clickElement($driver, '#Form_CTID_SHOW_3');
            sleep(5);

            $this->clickElement($driver, 'button.btn-orange[title="送出"]');
            $this->waitForTableHasRows($driver, 30);

            try {
                $body = $driver->findElement(WebDriverBy::tagName('body'));
                $actions = new WebDriverActions($driver);
                $actions->sendKeys($body, WebDriverKeys::ESCAPE)->perform();
            } catch (\Exception $e) {
            }

            $pageSizeSelect = $driver->findElement(WebDriverBy::cssSelector('#PagingInfo_PageSize'));
            $driver->executeScript("arguments[0].value = '100'; arguments[0].dispatchEvent(new Event('change'));", [$pageSizeSelect]);
            sleep(5);
            $this->clickElement($driver, '#btnGoRecCount');

            $driver->wait(15)->until(function ($driver) {
                $rows = $driver->findElements(WebDriverBy::cssSelector('table tbody tr'));
                return count($rows) > 15;
            });

            $this->waitForTableHasRows($driver, 20);
            $rows = $driver->findElements(WebDriverBy::xpath('//table//tr[@valign="middle"]'));

            foreach ($rows as $row) {
                try {
                    $cols = $row->findElements(WebDriverBy::tagName('td'));
                    if (count($cols) < 12) {
                        continue;
                    }

                    $linkElement = $cols[1]->findElement(WebDriverBy::xpath('.//a[contains(text(), "另開新視窗")]'));
                    $href = trim($linkElement->getAttribute('href'));
                    $trainingUnitUrl = strpos($href, 'http') === 0 ? $href : 'https://ojt.wda.gov.tw'.$href;

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
                    $registrationDate = trim($registrationDateParts[0]).' ~ '.trim($registrationDateParts[1]);

                    $enrollmentStatus = trim($driver->executeScript('return arguments[0].innerText;', [$cols[9]]));
                    $scheduledPeriod = trim($driver->executeScript('return arguments[0].innerText;', [$cols[10]]));
                    $scheduledPeriodParts = explode('|', $scheduledPeriod);
                    $scheduledPeriod = trim($scheduledPeriodParts[0]).' ~ '.trim($scheduledPeriodParts[1]);

                    $paymentMethod = trim($driver->executeScript('return arguments[0].innerText;', [$cols[11]]));

                    $items[] = [
                        'training_unit_url' => $trainingUnitUrl,
                        'course_code' => $courseCode,
                        'course_name' => $courseName,
                        'training_count' => $trainingCount,
                        'training_hours' => $trainingHours,
                        'student_cost' => $studentCost,
                        'government_cost' => $governmentCost,
                        'training_location' => $trainingLocation,
                        'registration_start_at' => $this->convertTaiwanDatetime($registrationDateParts[0] ?? ''),
                        'registration_end_at' => $this->convertTaiwanDatetime($registrationDateParts[1] ?? ''),
                        'enrollment_status' => $enrollmentStatus,
                        'scheduled_start_date' => $this->convertTaiwanDate($scheduledPeriodParts[0] ?? ''),
                        'scheduled_end_date' => $this->convertTaiwanDate($scheduledPeriodParts[1] ?? ''),
                        'payment_method' => $paymentMethod,
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }
        } finally {
            $driver->quit();
        }

        return $items;
    }

    public function filter(array $item): bool
    {
        return $this->filter->shouldKeep($item);
    }

    public function format(array $item): string
    {
        return $this->formatter->format($item);
    }

    public function save(array $items): void
    {
        foreach ($items as $item) {
            $this->saveItem($item);
        }
    }

    protected function saveItem(array $item): ?array
    {
        if (empty($item['training_unit_url']) || GovOjt::where('training_unit_url', $item['training_unit_url'])->exists()) {
            return null;
        }

        $course = GovOjt::create($item);
        $this->notifyNewCourse($course);

        return $course->toArray();
    }

    protected function notifyNewCourse(GovOjt $course): void
    {
        $message = $this->formatter->format([
            'course_name' => $course->course_name,
            'registration_start_at' => $course->registration_start_at ? $course->registration_start_at->format('Y-m-d') : null,
            'training_unit_url' => $course->training_unit_url,
        ]);

        $this->telegramService->sendMessage(TelegramService::OJT_GROUP_CHAT_ID, $message);
    }

    protected function clickElement(RemoteWebDriver $driver, string $cssSelector): void
    {
        $this->waitForElement($driver, $cssSelector);
        $element = $driver->findElement(WebDriverBy::cssSelector($cssSelector));
        $element->click();
    }

    protected function waitForTableHasRows(RemoteWebDriver $driver, int $timeout = 30)
    {
        $driver->wait($timeout)->until(function ($driver) {
            $rows = $driver->findElements(WebDriverBy::cssSelector('table tbody tr'));
            return count($rows) > 0;
        });
    }

    protected function waitForElement(RemoteWebDriver $driver, string $cssSelector, int $timeout = 10)
    {
        $wait = new \Facebook\WebDriver\WebDriverWait($driver, $timeout);

        return $wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector($cssSelector)
            )
        );
    }

    protected function convertTaiwanDatetime(string $taiwanDatetime): ?string
    {
        $taiwanDatetime = trim($taiwanDatetime);
        if ($taiwanDatetime === '') {
            return null;
        }

        $parts = explode(' ', $taiwanDatetime);
        [$y, $m, $d] = explode('/', $parts[0]);
        $hour = $parts[1] ?? '00:00:00';

        return (intval($y) + 1911)."-$m-$d $hour";
    }

    protected function convertTaiwanDate(string $taiwanDate): ?string
    {
        $taiwanDate = trim($taiwanDate);
        if ($taiwanDate === '') {
            return null;
        }

        [$y, $m, $d] = explode('/', $taiwanDate);

        return (intval($y) + 1911)."-$m-$d";
    }
}
