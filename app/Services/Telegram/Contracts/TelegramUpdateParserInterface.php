<?php

namespace App\Services\Telegram\Contracts;

interface TelegramUpdateParserInterface
{
    public function parseUpdate(array $payload): array;
}
