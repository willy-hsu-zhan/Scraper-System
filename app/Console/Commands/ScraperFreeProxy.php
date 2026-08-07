<?php

namespace App\Console\Commands;

use App\Services\Scraper\FreeProxy\FreeProxyScraper;
use Illuminate\Console\Command;

class ScraperFreeProxy extends Command
{
    protected $signature = 'app:scraper-free-proxy';
    protected $description = 'Fetch a list of publicly available free proxies';

    public function handle()
    {
        $scraper = new FreeProxyScraper();
        $proxies = $scraper->run();

        $this->info('Found '.count($proxies).' proxies.');

        if (! empty($proxies)) {
            $this->info('Example: '.$scraper->format($proxies[0]));
        }
    }
}
