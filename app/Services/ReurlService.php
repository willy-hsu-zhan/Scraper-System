<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReurlService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.reurl.api_key');
    }

    /**
     * 產生短網址
     */
    public function shorten(string $url, array $utm = []): ?string
    {
        if (!$this->apiKey) {
            Log::warning('Reurl API key missing');

            return null;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'reurl-api-key' => $this->apiKey,
            ])->post('https://api.reurl.cc/shorten', array_merge([
                'url' => $url,
            ], $utm));

            if ($response->successful()) {
                $data = $response->json();

                if (($data['res'] ?? null) === 'success') {
                    return $data['short_url'];
                }
            }

            Log::warning('Reurl shorten failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Reurl API error', [
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
