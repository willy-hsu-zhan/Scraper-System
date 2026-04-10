<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GovOjt;
use Illuminate\Support\Facades\Log;
use App\Services\LineNotifyService;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use Carbon\Carbon;

class NotifyOpenGovCourses extends Command
{
    protected $signature = 'app:notify-open-govojt';

    protected $description = '通知今天開放報名的政府訓練課程';

    protected $lineNotifyService;
    protected $telegramService;
    protected $seleniumService;
    protected $chatId = '-4937576377';

    public function __construct(LineNotifyService $lineNotifyService, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotifyService = $lineNotifyService;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $now = Carbon::now('Asia/Taipei');

        // 通知：三天後的一整天（00:00 ~ 23:59）
        $this->notifyCourses(
            $now->copy()->addDays(3)->startOfDay(),
            $now->copy()->addDays(3)->endOfDay(),
            'notified_3days_before',
            '📢 課程將在三天後開放報名！'
        );

        // 通知：明天的一整天
        $this->notifyCourses(
            $now->copy()->addDay()->startOfDay(),
            $now->copy()->addDay()->endOfDay(),
            'notified_1day_before',
            '📢 課程將在明天開放報名！'
        );

        // 通知：下一個完整小時（例如現在是 13:15 就通知 14:00 ~ 14:59）
        $startHour = $now->copy()->addHour()->startOfHour();
        $endHour = $now->copy()->addHour()->endOfHour();
        $this->notifyCourses(
            $startHour,
            $endHour,
            'notified_1hour_before',
            '⏰ 課程將在一小時後開放報名！'
        );

        // 補通知漏掉的
        $this->notifyMissedCourses($now->copy(), '📣 課程已開放報名（補通知）');

        $this->info("✅ 所有時間點的課程通知完成。");
        return Command::SUCCESS;
    }

    protected function notifyCourses($startTime, $endTime, $notifyField, $prefix)
    {
        $courses = GovOjt::whereBetween('registration_start_at', [$startTime, $endTime])
                        ->where(function ($query) use ($notifyField) {
                            $query->whereNull($notifyField)->orWhere($notifyField, '');
                        })
                        ->get();

        foreach ($courses as $course) {
            $calendarLink = $this->generateGoogleCalendarLink($course);
            $message = "{$prefix}\n\n" .
                    "📘 *課程名稱：* {$course->course_name}\n" .
                    "📅 *訓練日期：* {$course->scheduled_start_date} ~ {$course->scheduled_end_date}\n" .
                    "🕒 *報名時間：* {$course->registration_start_at}\n" .
                    "📍 *地點：* {$course->training_location}\n" .
                    "💰 *學員自付：* {$course->student_cost} 元\n" .
                    "🔗 *報名網址：* {$course->training_unit_url}\n".
                    "📅 *加入 Google 日曆提醒：* {$calendarLink}";

            sleep(5);

            $this->telegramService->sendMessage($this->chatId, $message);

            $course->update([
                $notifyField => now('Asia/Taipei')
            ]);

            Log::info("🔔 已發送「{$prefix}」通知：{$course->course_name}");
            $this->info("✅ 已通知：{$course->course_name} ({$notifyField})");
        }
    }

    protected function notifyMissedCourses($now, $prefix)
    {
        $courses = GovOjt::where('registration_start_at', '<=', $now)
                    ->whereNull('notified_3days_before')
                    ->whereNull('notified_1day_before')
                    ->whereNull('notified_1hour_before')
                    ->get();

        foreach ($courses as $course) {
            $calendarLink = $this->generateGoogleCalendarLink($course);
            $message = "{$prefix}\n\n" .
                    "📘 *課程名稱：* {$course->course_name}\n" .
                    "📅 *訓練日期：* {$course->scheduled_start_date} ~ {$course->scheduled_end_date}\n" .
                    "🕒 *報名時間：* {$course->registration_start_at}\n" .
                    "📍 *地點：* {$course->training_location}\n" .
                    "💰 *學員自付：* {$course->student_cost} 元\n" .
                    "🔗 *報名網址：* {$course->training_unit_url}\n".
                    "📅 *加入 Google 日曆提醒：* {$calendarLink}";

            sleep(2);

            $this->telegramService->sendMessage($this->chatId, $message);

            // 只記錄補通知時間到其中一個欄位，例如 notified_1hour_before
            $course->update([
                'notified_1hour_before' => now('Asia/Taipei')
            ]);

            Log::info("📣 已補發通知：{$course->course_name}");
            $this->info("✅ 已補通知：{$course->course_name}");
        }
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
        $end = $start->copy()->addHour(); // 事件給 1 小時

        $startUTC = $start->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $endUTC = $end->copy()->setTimezone('UTC')->format('Ymd\THis\Z');

        $dates = "{$startUTC}/{$endUTC}";

        $url = "https://calendar.google.com/calendar/render?action=TEMPLATE";
        $url .= "&text={$title}";
        $url .= "&dates={$dates}";
        $url .= "&details={$details}";
        $url .= "&location={$location}";
        $url .= "&sf=true&output=xml";

        return $url;
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
}
