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

        $this->app->bind(
            \App\Services\Telegram\Routers\TelegramMessageRouter::class,
            \App\Services\Telegram\Routers\TelegramMessageRouter::class
        );

        $this->app->bind(
            \App\Services\Telegram\Routers\TelegramCallbackRouter::class,
            \App\Services\Telegram\Routers\TelegramCallbackRouter::class
        );

        $this->app->bind(
            \App\Services\Telegram\Handlers\OjtHandler::class,
            \App\Services\Telegram\Handlers\OjtHandler::class
        );

        $this->app->bind(
            \App\Services\Telegram\Handlers\AuctionHandler::class,
            \App\Services\Telegram\Handlers\AuctionHandler::class
        );

        $this->app->bind(
            \App\Services\Telegram\Handlers\MusicHandler::class,
            \App\Services\Telegram\Handlers\MusicHandler::class
        );

        $this->app->bind(
            \App\Services\Telegram\Handlers\YoutubeHandler::class,
            \App\Services\Telegram\Handlers\YoutubeHandler::class
        );

        $this->app->bind(
            \App\Services\Telegram\Handlers\ReportHandler::class,
            \App\Services\Telegram\Handlers\ReportHandler::class
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
