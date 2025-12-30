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
        if (app()->environment('local')) {
            $schedule->command('app:scraper-ojt')
                ->hourly()
                ->timezone('Asia/Taipei'); // 每天 00:00、12:00
        }

        if (!app()->environment('production')) {
            return;
        }

        $schedule->command('app:search-youtube-music')
            ->dailyAt('12:15')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/search-youtube-music.log'))
            ->timezone('Asia/Taipei');

        $schedule->command('app:scraper-delivery-coupon')
            ->daily()
            ->timezone('Asia/Taipei');

        $schedule->command('app:scraper-birthday-discount')
            ->twiceDaily(10, 22)
            ->timezone('Asia/Taipei');

        $schedule->command('app:scraper-chickpt')
            ->cron('15,45 * * * *')
            ->timezone('Asia/Taipei')
            ->when(function () {
                $hour = now('Asia/Taipei')->hour;
                return !($hour >= 1 && $hour < 7);
            });
        //$schedule->command('app:scraper-ptt')->cron('15,45 * * * *');
        $schedule->command('app:scraper-rent591')->everySixHours()->timezone('Asia/Taipei');
        $schedule->command('app:notify-open-govojt')->hourly()->timezone('Asia/Taipei');
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
