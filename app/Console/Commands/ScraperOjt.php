<?php

namespace App\Console\Commands;

use App\Models\GovOjt;
use App\Services\GeminiService;
use App\Services\ReurlService;
use App\Services\Scraper\Ojt\OjtScraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScraperOjt extends Command
{
    protected $signature = 'app:scraper-ojt';

    protected $description = 'Scrape OJT courses and send Telegram notifications';

    protected $telegramService;

    protected $seleniumService;

    protected $geminiService;

    protected $reurlService;

    protected $chatId = TelegramChat::OJT;

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
        $scraper = new OjtScraper(
            $this->seleniumService,
            $this->geminiService,
            $this->reurlService,
            $this->telegramService
        );

        $newCourses = $scraper->run();

        foreach ($newCourses as $course) {
            $govOjt = GovOjt::where('training_unit_url', $course['training_unit_url'] ?? null)->first();

            try {
                if ($govOjt && ! empty($govOjt->training_unit_url)) {
                    $summary = $this->geminiService->summarizeCourseByUrl($govOjt->training_unit_url);

                    if (! empty($summary)) {
                        // 儲存 AI 摘要
                        $govOjt->update([
                            'ai_summary' => $summary,
                        ]);

                        // 發送 AI 摘要 Telegram 通知
                        $summaryMessage = "🧠 AI 課程分析\n\n";
                        $summaryMessage .= $summary;

                        $this->telegramService->sendMessage(
                            $this->chatId,
                            $summaryMessage
                        );

                        sleep(3);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('AI summary generation failed', [
                    'course' => $course['course_name'] ?? null,
                    'url' => $course['training_unit_url'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            $message = "課程名稱：{$course['course_name']}\n";
            $message .= "課程代碼：{$course['course_code']}\n";
            $message .= "訓練單位：{$course['training_unit_url']}\n";
            $message .= "招生名額：{$course['training_count']}\n";
            $message .= "訓練時數：{$course['training_hours']}\n";
            $message .= "學員費用：{$course['student_cost']}\n";
            $message .= "政府補助：{$course['government_cost']}\n";
            $message .= "訓練地點：{$course['training_location']}\n";
            $message .= "報名期間：{$this->formatDatetime($course['registration_start_at'])} ~ {$this->formatDatetime($course['registration_end_at'])}\n";
            $message .= "招生狀態：{$course['enrollment_status']}\n";
            $message .= "開課期間：{$this->formatDate($course['scheduled_start_date'])} ~ {$this->formatDate($course['scheduled_end_date'])}\n";
            $message .= "繳費方式：{$course['payment_method']}";

            $keyboard = null;

            if ($govOjt) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'AI 摘要',
                                'callback_data' => "ojt_summary:{$govOjt->id}",
                            ],
                        ],
                    ],
                ];
            }

            $this->telegramService->sendMessage(
                $this->chatId,
                $message,
                $keyboard
            );

            sleep(3);
        }

        $this->info('已檢查 ' . count($newCourses) . ' 個新的 OJT 課程。');

        return 0;
    }

    private function formatDatetime($datetime): string
    {
        if (empty($datetime)) {
            return '未提供';
        }

        return Carbon::parse($datetime)
            ->timezone('Asia/Taipei')
            ->format('Y-m-d H:i');
    }

    private function formatDate($date): string
    {
        if (empty($date)) {
            return '未提供';
        }

        return Carbon::parse($date)
            ->timezone('Asia/Taipei')
            ->format('Y-m-d');
    }
}
