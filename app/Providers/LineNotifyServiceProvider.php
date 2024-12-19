<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\LineNotify;

class LineNotifyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LineNotify::class, function ($app) {
            return new LineNotify(config('services.line_notify.token'));
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
