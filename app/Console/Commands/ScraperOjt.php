<?php

namespace App\Console\Commands;

use App\Models\GovOjt;
use App\Services\GeminiService;
use App\Services\ReurlService;
use App\Services\Scraper\Ojt\OjtScraper;
use App\Services\SeleniumService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScraperOjt extends Command
{
    protected $signature = 'app:scraper-ojt';
    protected $description = '抓取台灣就業通產業人才投資方案課程資訊，並發送 Telegram 通知';

    protected $telegramService;
    protected $seleniumService;
    protected $geminiService;
    protected $reurlService;
    protected $chatId = TelegramService::OJT_GROUP_CHAT_ID;

    public function __construct(
        TelegramService $telegramService,
        SeleniumService $seleniumService,
        GeminiService $geminiService,
        ReurlService $reurlService
    ) {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
        $this->geminiService = $geminiService;
        $this->reurlService = $reurlService;
    }

    public function handle()
    {
        $scraper = new OjtScraper($this->seleniumService, $this->geminiService, $this->reurlService, $this->telegramService);
        $newCourses = $scraper->run();

        foreach ($newCourses as $course) {
            $govOjt = GovOjt::where('training_unit_url', $course['training_unit_url'])->first();

            try {
                if ($govOjt && ! empty($govOjt->training_unit_url)) {
                    $summary = $this->geminiService->summarizeCourseByUrl($govOjt->training_unit_url);
                    if (! empty($summary)) {
                        $govOjt->update(['ai_summary' => $summary]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('AI 摘要失敗', [
                    'course' => $course['course_name'] ?? null,
                    'url' => $course['training_unit_url'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            $message = '【課程名稱】'.$course['course_name']."\n";
            $message .= '【課程代碼】'.$course['course_code']."\n";
            $message .= '【訓練班別網址】'.$course['training_unit_url']."\n";
            $message .= '【訓練人數】'.$course['training_count']."\n";
            $message .= '【訓練時數】'.$course['training_hours']."\n";
            $message .= '【學員負擔】'.$course['student_cost']."\n";
            $message .= '【政府負擔】'.$course['government_cost']."\n";
            $message .= '【訓練地點】'.$course['training_location']."\n";
            $message .= '【報名日期】'.$course['registration_start_at'].' ~ '.$course['registration_end_at']."\n";
            $message .= '【招生狀態】'.$course['enrollment_status']."\n";
            $message .= '【預訂訓練期間】'.$course['scheduled_start_date'].' ~ '.$course['scheduled_end_date']."\n";
            $message .= '【報名繳費方式】'.$course['payment_method'];

            if ($govOjt) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '🧠 AI 課程分析',
                                'callback_data' => "ojt_summary:{$govOjt->id}",
                            ],
                        ],
                    ],
                ];
            } else {
                $keyboard = null;
            }

            $this->telegramService->sendMessage($this->chatId, $message, $keyboard);
            sleep(3);
        }

        $this->info('完成，共抓取 '.count($newCourses).' 筆');
    }
}
