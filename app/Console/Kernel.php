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
        //$schedule->command('app:scraper-ptt')->cron('15,45 * * * *'); // 改為每小時的15,45分執行使任務不衝突
        //$schedule->command('app:search-youtube-music')->dailyAt('18:05')->timezone('Asia/Taipei'); // 每天6.05抓最新熱門歌單
        $schedule->command('app:scraper-rent591')->everySixHours()->timezone('Asia/Taipei'); // 每6小時執行一次
        $schedule->command('app:notify-today-open-govojt')->hourly()->timezone('Asia/Taipei'); // 通知三天前、一天前、一小時前的課程報名
        $schedule->command('app:scraper-ojt TPE')->dailyAt('03:00')->timezone('Asia/Taipei');; // 通知並記錄將來開放的台北課程
        $schedule->command('app:scraper-ojt KEE')->dailyAt('03:30')->timezone('Asia/Taipei');; // 通知並記錄將來開放的基隆課程
        $schedule->command('app:scraper-ojt NWT')->dailyAt('04:00')->timezone('Asia/Taipei');; // 通知並記錄將來開放的新北課程
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
