<?php

namespace App\Services\Telegram\Contracts;

interface TelegramMessageFormatterInterface
{
    public function formatTelegramText(string $text): string;
}
