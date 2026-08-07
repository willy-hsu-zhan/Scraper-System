<?php

namespace App\Services\AI\Contracts;

interface GeminiClientInterface
{
    public function request(array $payload): ?array;
}
