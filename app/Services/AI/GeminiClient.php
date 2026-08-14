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
        $this->apiKey = (string) config('gemini.api_key', '');
        $this->baseUrl = (string) config('gemini.base_url', '');
        $this->timeout = (int) config('gemini.timeout', 60);

        if ($this->apiKey === '' || $this->baseUrl === '') {
            $this->apiKey = '';
            $this->baseUrl = '';
        }
    }

    public function request(array $payload): ?array
    {
        if ($this->apiKey === '' || $this->baseUrl === '') {
            return null;
        }

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

        return rtrim($this->baseUrl, '/')."/models/{$model}:generateContent?key={$this->apiKey}";
    }
}
