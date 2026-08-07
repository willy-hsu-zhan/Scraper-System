<?php

namespace App\Console\Commands;

use App\Services\Scraper\DeliveryCoupon\DeliveryCouponScraper;
use App\Services\SeleniumService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ScraperDeliveryCoupon extends Command
{
    protected $signature = 'app:scraper-delivery-coupon';
    protected $description = 'Collect delivery coupon and discount codes';

    protected $telegramService;
    protected $seleniumService;
    protected $chatId = '-5419894094';

    public function __construct(TelegramService $telegramService, SeleniumService $seleniumService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->seleniumService = $seleniumService;
    }

    public function handle()
    {
        $scraper = new DeliveryCouponScraper($this->seleniumService);
        $newCoupons = $scraper->run();

        if (! empty($newCoupons)) {
            foreach ($newCoupons as $coupon) {
                $this->telegramService->sendMessage($this->chatId, $scraper->format($coupon));
                sleep(2);
            }
        }

        $this->info('完成，共抓取 '.count($newCoupons).' 筆');
    }
}
