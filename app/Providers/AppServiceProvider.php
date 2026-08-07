<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\AI\Contracts\GeminiClientInterface::class,
            \App\Services\AI\GeminiClient::class
        );

        $this->app->bind(
            \App\Services\AI\Contracts\GeminiPromptBuilderInterface::class,
            \App\Services\AI\GeminiPromptBuilder::class
        );

        $this->app->bind(
            \App\Services\Telegram\Contracts\TelegramApiClientInterface::class,
            \App\Services\Telegram\TelegramApiClient::class
        );

        $this->app->bind(
            \App\Services\Telegram\Contracts\TelegramMessageFormatterInterface::class,
            \App\Services\Telegram\TelegramMessageFormatter::class
        );

        $this->app->bind(
            \App\Services\Telegram\Contracts\TelegramUpdateParserInterface::class,
            \App\Services\Telegram\TelegramUpdateParser::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
