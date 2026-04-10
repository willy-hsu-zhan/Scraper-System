<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\LineNotifyService;

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
