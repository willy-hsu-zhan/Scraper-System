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
        // 統一使用 Laravel application timezone，避免排程時間與應用程式時區不一致。
        $timezone = config('app.timezone', 'Asia/Taipei');

        // YouTube 搜尋結果的執行紀錄獨立寫入檔案，方便查看排程執行狀況。
        $logPath = storage_path('logs/search-youtube.log');

        // 就業通課程：每小時更新一次課程資料。
        $schedule->command('app:scraper-ojt')
            ->hourly()
            ->timezone($timezone);

        // YouTube 每日搜尋排程。
        // 將相同設定集中管理，避免重複撰寫排程設定。
        $youtubeSchedules = [
            ['command' => 'TW 19', 'time' => '12:10'], // 旅遊
            ['command' => 'all 10', 'time' => '12:20'], // 音樂
            ['command' => 'TW 20', 'time' => '12:25'], // 遊戲
            ['command' => 'TW 24', 'time' => '12:35'], // 電影
        ];

        foreach ($youtubeSchedules as $config) {
            $schedule->command("app:search-youtube {$config['command']}")
                ->dailyAt($config['time'])
                ->withoutOverlapping()
                ->onOneServer()
                ->runInBackground()
                ->appendOutputTo($logPath)
                ->timezone($timezone);
        }

        // 4Gamers 免費遊戲：每天上午 9 點與晚上 9 點執行。
        $schedule->command('app:scraper-4gamers-free')
            ->twiceDaily(9, 21)
            ->timezone($timezone);

        // 外送優惠：每天執行一次。
        $schedule->command('app:scraper-delivery-coupon')
            ->daily()
            ->timezone($timezone);

        // 生日優惠：每天上午 10 點與晚上 10 點執行。
        $schedule->command('app:scraper-birthday-discount')
            ->twiceDaily(10, 22)
            ->timezone($timezone);

        // Chickpt：每小時的 15 分與 45 分執行。
        // 凌晨 01:00～07:00 暫停執行，避免不必要的請求。
        $schedule->command('app:scraper-chickpt')
            ->cron('15,45 * * * *')
            ->timezone($timezone)
            ->when(function () use ($timezone) {
                $hour = now($timezone)->hour;

                return ! ($hour >= 1 && $hour < 7);
            });

        // 法拍資訊：每天凌晨 00:30 執行。
        $schedule->command('app:scraper-judicial-auction')
            ->dailyAt('00:30')
            ->timezone($timezone);

        // PTT：每小時的 10 分與 30 分執行。
        $schedule->command('app:scraper-ptt')
            ->cron('10,30 * * * *')
            ->timezone($timezone);

        // YouTube 暫存檔案：每天凌晨 02:00 清理 1 天前的檔案。
        $schedule->command('youtube:clean')
            ->dailyAt('02:00')
            ->timezone($timezone);

        // 591 租屋資訊：每 30 分鐘更新一次。
        $schedule->command('app:scraper-rent591')
            ->everyThirtyMinutes()
            ->timezone($timezone);

        // 就業通公開課程通知：每小時檢查一次是否有需要通知的新課程。
        $schedule->command('app:notify-open-govojt')
            ->hourly()
            ->timezone($timezone);
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
