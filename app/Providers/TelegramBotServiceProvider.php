<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TelegramBot;

class TelegramBotServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(TelegramBot::class, function ($app) {
            return new TelegramBot(token: config('services.line_notify.token'));
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
