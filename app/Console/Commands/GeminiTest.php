<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GeminiService;

class GeminiTest extends Command
{
    protected $signature = 'app:gemini-test {text?}';
    protected $description = 'Test Gemini text summarization';

    protected $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        parent::__construct();
        $this->geminiService = $geminiService;
    }

    public function handle()
    {
        $text = $this->argument('text');

        if (!$text) {
            $text = "
        ";
        }

        $this->info("🧠 輸入內容：");
        $this->line($text);

        $this->info("🤖 呼叫 Gemini...");

        $url = 'https://ojt.wda.gov.tw/ClassSearch/Detail?PlanType=1&OCID=173299';
        try {
            //$summary = $this->geminiService->summarizeText($text);
            $summary = $this->geminiService->summarizeCourseByUrl($url);
            if (!$summary) {
                $this->error("❌ Gemini 回傳為空（null）");
                return 1;
            }

            $this->info("✅ AI 摘要結果：");
            $this->line("--------------------------------------------------");
            $this->line($summary);
            $this->line("--------------------------------------------------");

            return 0;
        } catch (\Throwable $e) {

            $this->error("🔥 Gemini 發生錯誤：");
            $this->line($e->getMessage());

            logger()->error('Gemini Error', [
                'message' => $e->getMessage(),
            ]);

            return 1;
        }
    }
}
