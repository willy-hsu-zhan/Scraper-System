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
        $schedule->command('app:scraper-ojt')
            ->hourly()
            ->timezone('Asia/Taipei'); // 每天 00:00、12:00

        //$logPath = storage_path('logs/search-youtube.log');

        // ✈️ 旅遊
        // $schedule->command('app:search-youtube TW 19')
        //     ->dailyAt('12:10')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground()
        //     ->appendOutputTo($logPath)
        //     ->timezone('Asia/Taipei');

        // 🎵 音樂
        // $schedule->command('app:search-youtube all 10')
        //     ->dailyAt('12:20')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground()
        //     ->appendOutputTo($logPath)
        //     ->timezone('Asia/Taipei');

        // 🎮 遊戲
        // $schedule->command('app:search-youtube TW 20')
        //     ->dailyAt('12:25')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground()
        //     ->appendOutputTo($logPath)
        //     ->timezone('Asia/Taipei');

        // 🎬 電影
        // $schedule->command('app:search-youtube TW 24')
        //     ->dailyAt('12:35')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground()
        //     ->appendOutputTo($logPath)
        //     ->timezone('Asia/Taipei');

        $schedule->command('app:scraper-4gamers-free')
            ->twiceDaily(9, 21)
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

        $schedule->command('app:scraper-judicial-auction')
            ->dailyAt('00:30')
            ->timezone('Asia/Taipei');
        $schedule->command('app:scraper-ptt')->cron('10,30 * * * *');
        // 每天凌晨 2 點清理 1 天前的檔案
        $schedule->command('youtube:clean')->dailyAt('02:00');
        $schedule->command('app:scraper-rent591')->everyThirtyMinutes()->timezone('Asia/Taipei');
        $schedule->command('app:notify-open-govojt')->hourly()->timezone('Asia/Taipei');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
