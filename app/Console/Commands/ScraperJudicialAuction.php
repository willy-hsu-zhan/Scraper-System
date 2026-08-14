<?php

namespace App\Console\Commands;

use App\Models\JudicialAuction;
use App\Services\GeminiService;
use App\Services\Scraper\JudicialAuction\JudicialAuctionScraper;
use App\Services\SeleniumService;
use App\Services\Telegram\TelegramChat;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScraperJudicialAuction extends Command
{
    protected $signature = 'app:scraper-judicial-auction';

    protected $description = 'Stable judicial auction scraper (multi-court safe version)';

    protected $telegramService;

    protected $seleniumService;

    protected $geminiService;
    protected $chatId = TelegramChat::AUCTION;

    public function __construct(
        TelegramService $telegramService,
        SeleniumService $seleniumService,
        GeminiService $geminiService
    ) {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
        $this->geminiService = $geminiService;
    }

    public function handle()
    {
        $scraper = new JudicialAuctionScraper($this->seleniumService);
        $newItems = $scraper->run();

        foreach ($newItems as $item) {
            $message = "🏠 新法拍案件\n\n";
            $message .= "🏛 法院：{$item['court']}\n";
            $message .= "📌 案號：{$item['case_no']}\n";
            $message .= "📅 拍賣日：{$item['auction_date']}\n\n";
            $message .= "📍 區域：{$item['area']}\n\n";
            $message .= "🏡 地址：{$item['address']}\n\n";
            $message .= "💰 價格：{$this->formatPriceToChinese((int) ($item['price'] ?? 0))}（{$item['price']}元）\n\n";
            $message .= '📦 點交：'.($item['is_handover'] ?? '未標示')."\n\n";
            $message .= "📄 PDF：{$item['pdf_url']}";

            $auction = JudicialAuction::where('court', $item['court'])
                ->where('case_no', $item['case_no'])
                ->first();

            if ($auction && ! empty($item['pdf_url'])) {
                try {
                    $summary = $this->geminiService->summarizePdfByUrl($item['pdf_url']);
                    if (! empty($summary)) {
                        $auction->update(['ai_summary' => $summary]);
                    }
                } catch (\Throwable $e) {
                    Log::error('AI 摘要失敗', [
                        'error' => $e->getMessage(),
                        'court' => $item['court'],
                        'case_no' => $item['case_no'],
                    ]);
                }
            }

            $keyboard = null;
            if ($auction) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '產生 AI 摘要',
                                'callback_data' => "auction_summary:{$auction->id}",
                            ],
                        ],
                    ],
                ];
            }

            $this->telegramService->sendMessage($this->chatId, $message, $keyboard);
            sleep(2);
        }

        $this->info('完成，共抓取 '.count($newItems).' 筆');
    }

    private function formatPriceToChinese(int $number): string
    {
        if ($number >= 10000) {
            $wan = intval($number / 10000);
            $rest = $number % 10000;

            return $rest > 0
                ? "{$wan}萬{$rest}元"
                : "{$wan}萬元";
        }

        return "{$number}元";
    }
}
