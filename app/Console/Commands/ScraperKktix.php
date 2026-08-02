<?php

namespace App\Console\Commands;

use App\Services\SeleniumService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ScraperKktix extends Command
{
    protected $signature = 'app:scraper-kktix';

    protected $description = 'KKTIX Selenium Scraper (Stable Version)';

    public function __construct(
        private SeleniumService $seleniumService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $event = 'loedge';

        // 改成附加到已開啟的 Chrome
        $driver = $this->seleniumService->attachChrome();

        try {

            $url = "https://kktix.com/events/{$event}/registrations/new";

            $this->info("Opening: {$url}");

            $driver->get($url);

            $driver->takeScreenshot(
                storage_path('app/kktix_1_before_cf.png')
            );

            $this->info("Waiting Cloudflare...");

            $this->waitUntilPassed($driver);

            $driver->takeScreenshot(
                storage_path('app/kktix_2_after_cf.png')
            );

            $api = "https://kktix.com/g/events/{$event}/base_info";

            $result = $driver->executeScript(<<<JS
        return (async () => {
            const res = await fetch("$api", {
                credentials: "include"
            });

            return await res.text();
        })();
        JS);

            if ($this->isBlocked($result)) {
                throw new \Exception("Still blocked");
            }

            Storage::disk('local')->put(
                'base_info.json',
                $result
            );

            $this->info("Success");
        } finally {
            $driver->quit();
        }
    }

    /**
     * 等待 Cloudflare 通過
     */
    private function waitUntilPassed($driver, $timeout = 60)
    {
        $this->info("Waiting Cloudflare clearance...");

        for ($i = 0; $i < $timeout; $i++) {

            $html = $driver->getPageSource();

            if (!$this->isBlocked($html)) {
                $this->info("Cloudflare passed ✔");
                return;
            }

            sleep(1);
        }

        throw new \Exception("Cloudflare timeout");
    }

    /**
     * 判斷是否還被擋
     */
    private function isBlocked($html): bool
    {
        return str_contains($html, 'Just a moment')
            || str_contains($html, 'Enable JavaScript')
            || str_contains($html, 'cf-chl')
            || str_contains($html, '<!DOCTYPE html>');
    }
}
