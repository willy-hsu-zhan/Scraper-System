<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GovOjt;
use Illuminate\Support\Facades\Log;
use App\Services\LineNotify;
use App\Services\TelegramService;
use App\Services\SeleniumService;
use Carbon\Carbon;

class NotifyTodayOpenGovCourses extends Command
{
    protected $signature = 'app:notify-today-open-govojt';

    protected $description = '通知今天開放報名的政府訓練課程';

    protected $lineNotify;
    protected $telegramService;
    protected $seleniumService;
    protected $chatId = '-4937576377';

    public function __construct(LineNotify $lineNotify, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotify = $lineNotify;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $now = Carbon::now('Asia/Taipei');

        $this->notifyCourses($now->copy()->addDays(3)->startOfDay(), 'notified_3days_before', '📢 課程將在三天後開放報名！');
        $this->notifyCourses($now->copy()->addDay()->startOfDay(), 'notified_1day_before', '📢 課程將在明天開放報名！');
        $this->notifyCourses($now->copy()->addHour()->format('Y-m-d H:00:00'), 'notified_1hour_before', '⏰ 課程將在一小時後開放報名！');

        $this->info("✅ 所有時間點的課程通知完成。");
        return Command::SUCCESS;
    }

    protected function notifyCourses($targetTime, $notifyField, $prefix)
    {
        $courses = GovOjt::where('registration_start_at', $targetTime)
                        ->whereNull($notifyField)
                        ->get();

        foreach ($courses as $course) {
            $message = "{$prefix}\n\n" .
                    "📘 *課程名稱：* {$course->course_name}\n" .
                    "📅 *訓練日期：* {$course->scheduled_start_date} ~ {$course->scheduled_end_date}\n" .
                    "📍 *地點：* {$course->training_location}\n" .
                    "💰 *學員自付：* {$course->student_cost} 元\n" .
                    "🔗 *報名網址：* {$course->training_unit_url}";

            $this->telegramService->sendMessage($this->chatId, $message);

            $course->update([$notifyField => now('Asia/Taipei')]);

            Log::info("🔔 已發送「{$prefix}」通知：{$course->course_name}");
            $this->info("✅ 已通知：{$course->course_name} ({$notifyField})");
        }
    }
}
