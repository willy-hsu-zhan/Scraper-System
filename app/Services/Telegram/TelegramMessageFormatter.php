<?php

namespace App\Services\Telegram;

use App\Services\Telegram\Contracts\TelegramMessageFormatterInterface;

class TelegramMessageFormatter implements TelegramMessageFormatterInterface
{
    public function formatTelegramText(string $text): string
    {
        $text = preg_replace('/^#+\s*/m', '', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/^\s*\*\s+/m', '• ', $text);
        $text = preg_replace('/^-{3,}$/m', '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
