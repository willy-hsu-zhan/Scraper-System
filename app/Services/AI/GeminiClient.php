<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\GeminiClientInterface;
use Illuminate\Support\Facades\Http;

class GeminiClient implements GeminiClientInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');
        $this->baseUrl = config('gemini.base_url');
        $this->timeout = config('gemini.timeout', 60);

        if (! $this->apiKey || ! $this->baseUrl) {
            throw new \RuntimeException('Gemini API configuration is missing.');
        }
    }

    public function request(array $payload): ?array
    {
        $endpoint = $this->buildEndpoint();

        $response = Http::timeout($this->timeout)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    protected function buildEndpoint(): string
    {
        $model = config('gemini.model');

        return rtrim($this->baseUrl, '/') . "/models/{$model}:generateContent?key={$this->apiKey}";
    }
}
