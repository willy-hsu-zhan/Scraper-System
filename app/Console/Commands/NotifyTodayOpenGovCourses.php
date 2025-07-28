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
    protected $chatId = '-4968260856';

    public function __construct(LineNotify $lineNotify, TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->lineNotify = $lineNotify;
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $today = Carbon::today('Asia/Taipei')->format('Y-m-d');

        $courses = GovOjt::whereDate('registration_start_at', $today)
                         ->whereNull('notified_at')
                         ->get();

        if ($courses->isEmpty()) {
            $this->info('✅ 今天沒有開放報名的新課程。');
            return Command::SUCCESS;
        }

        foreach ($courses as $course) {
            $message = "📢 *新課程開放報名！*\n\n" .
                       "📘 *課程名稱：* {$course->course_name}\n" .
                       "📅 *訓練日期：* {$course->scheduled_start_date} ~ {$course->scheduled_end_date}\n" .
                       "📍 *地點：* {$course->training_location}\n" .
                       "💰 *學員自付：* {$course->student_cost} 元\n" .
                       "🔗 *報名網址：* {$course->training_unit_url}";

            // 發送到 Telegram 群組
            $this->telegramService->sendMessage($this->chatId, $message);

            // 記錄已通知
            $course->update(['notified_at' => now('Asia/Taipei')]);

            Log::info("🎓 已通知：{$course->course_name}");
            $this->info("✅ 已通知：{$course->course_name}");
        }

        $this->info("🎯 共發送通知 {$courses->count()} 筆開放報名的課程。");
        return Command::SUCCESS;
    }
}
