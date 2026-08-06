<?php

namespace App\Providers;

use App\Services\LineNotifyService;
use Illuminate\Support\ServiceProvider;

class LineNotifyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LineNotifyService::class, function ($app) {
            return new LineNotifyService(config('services.line_notify.token'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
