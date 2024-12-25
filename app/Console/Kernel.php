<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:scraper-chickpt')->everyThirtyMinutes();
        $schedule->command('app:scraper-ptt')->hourly(); // 更新速度較慢 改每小時
        //$schedule->command('app:delete-old-record')->dailyAt('00:15')->timezone('Asia/Taipei');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
