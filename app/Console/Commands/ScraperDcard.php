<?php

namespace App\Console\Commands;

use App\Services\LineNotifyService;
use App\Services\ProxyService;
use App\Services\TelegramService;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ScraperDcard extends Command
{
    public $jobs = [];

    protected $lineNotifyService;

    protected $telegramService;

    protected $proxyService;

    protected $jobTexts;

    protected $chatId = '-4664862172'; // 暫時先用全域變數 // 日後聊天室擴充再更改

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-dcard';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(LineNotifyService $lineNotifyService, TelegramService $telegramService, ProxyService $proxyService)
    {
        parent::__construct();
        $this->lineNotifyService = $lineNotifyService;
        $this->telegramService = $telegramService;
        $this->proxyService = $proxyService;
    }

    public function handle()
    {
        // 獲取代理
        $proxy = $this->proxyService->getRamdomProxy();

        // 使用 Http facade 進行請求並設定代理
        $response = Http::withOptions([
            'proxy' => $proxy,          // 設定代理
            'timeout' => 6000,            // 設定超時時間（以秒為單位）
            'connect_timeout' => 600,    // 設定連線超時
        ])->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ])->get('https://www.dcard.tw/f/food?tab=latest');

        // 獲取網頁內容
        $content = $response->body();
        // 使用 Goutte 客戶端分析 HTML 內容
        $client = new Client();
        $crawler = $client->request('GET', 'data:text/html,'.urlencode($content));

        // 顯示抓取的內容
        dd($crawler->text());
    }
}
