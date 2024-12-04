<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;

class ScraperFacebook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scraper-facebook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return 0;
        Artisan::call('dusk', [
            '--filter' => 'FacebookLoginTest',
        ]);
    }
}
