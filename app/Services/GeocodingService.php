<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    protected string $baseUrl;
    protected string $userAgent;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = (string) config('geocoding.nominatim_url', 'https://nominatim.openstreetmap.org');
        $this->userAgent = (string) config('geocoding.user_agent', 'Scraper-System Telegram Bot');
        $this->timeout = (int) config('geocoding.timeout', 10);
    }

    /**
     * Reverse geocode lat/lon to a human-readable address using Nominatim.
     * Returns array with 'address' (string) and optional 'display_name' and 'raw' on success, or null on failure.
     */
    public function reverse(float $lat, float $lon): ?array
    {
        Log::info('Reverse geocoding', ['latitude' => $lat, 'longitude' => $lon]);

        try {
            $resp = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => $this->userAgent,
                ])
                ->get(rtrim($this->baseUrl, '/') . '/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lon,
                    'accept-language' => 'zh-TW;en',
                ]);

            if (! $resp->successful()) {
                Log::error('Reverse geocoding failed', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);

                return null;
            }

            $data = $resp->json();

            $display = $data['display_name'] ?? null;

            $addressParts = [];
            if (isset($data['address'])) {
                $addr = $data['address'];
                // prioritize locality fields
                foreach (['city', 'town', 'village', 'county', 'state', 'country'] as $k) {
                    if (isset($addr[$k])) {
                        $addressParts[] = $addr[$k];
                    }
                }
            }

            $address = $display ?? implode(', ', $addressParts);

            Log::info('Reverse geocoding success', [
                'latitude' => $lat,
                'longitude' => $lon,
                'address' => $address,
            ]);

            return [
                'address' => $address,
                'display_name' => $display,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Reverse geocoding failed', [
                'latitude' => $lat,
                'longitude' => $lon,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
