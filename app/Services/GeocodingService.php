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

            // Log raw results for debugging
            Log::info('Nominatim raw display_name', ['display_name' => $display]);
            Log::info('Nominatim raw address', ['address' => $data['address'] ?? null]);

            $address = null;
            if (isset($data['address']) && is_array($data['address'])) {
                $addr = $data['address'];

                // If this is a Taiwan address, try to format in local readable way
                $formatted = $this->formatTaiwanAddress($addr);
                if ($formatted !== null) {
                    $address = $formatted;
                    Log::info('Formatted Taiwan address', ['formatted' => $address]);
                } else {
                    // Generic composer: prefer house_number + road, then locality
                    $parts = [];

                    if (!empty($addr['house_number']) && !empty($addr['road'])) {
                        $parts[] = $addr['road'] . (str_ends_with($addr['road'], '路') || str_ends_with($addr['road'], '街') ? '' : '') . ($addr['house_number'] ? $addr['house_number'] : '');
                    } elseif (!empty($addr['road'])) {
                        $parts[] = $addr['road'];
                        if (!empty($addr['house_number'])) {
                            $parts[] = $addr['house_number'];
                        }
                    } elseif (!empty($addr['pedestrian'])) {
                        $parts[] = $addr['pedestrian'];
                        if (!empty($addr['house_number'])) {
                            $parts[] = $addr['house_number'];
                        }
                    }

                    // then add locality fields
                    foreach (['village', 'suburb', 'town', 'city_district', 'city', 'county', 'state'] as $k) {
                        if (!empty($addr[$k]) && !in_array($addr[$k], $parts, true)) {
                            $parts[] = $addr[$k];
                        }
                    }

                    if (!empty($parts)) {
                        $address = implode('', $parts);
                        Log::info('Generic formatted address', ['formatted' => $address]);
                    }
                }
            }

            // fallback to display_name if we couldn't compose an address
            if ($address === null) {
                $address = $display ?? null;
            }

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

    /**
     * Attempt to format Taiwan addresses from Nominatim address object into a natural Taiwan address string.
     * Returns formatted string or null if it doesn't look like a Taiwan address or cannot be formatted.
     */
    protected function formatTaiwanAddress(array $addr): ?string
    {
        $country = (string) ($addr['country'] ?? '');
        $countryCode = (string) ($addr['country_code'] ?? '');
        $isTaiwan = false;

        if ($country !== '') {
            $lower = mb_strtolower($country);
            if (str_contains($lower, 'taiwan') || str_contains($lower, '臺灣') || str_contains($lower, '台灣')) {
                $isTaiwan = true;
            }
        }

        if ($countryCode !== '' && mb_strtolower($countryCode) === 'tw') {
            $isTaiwan = true;
        }

        if (! $isTaiwan) {
            return null;
        }

        $city = trim((string) ($addr['city'] ?? $addr['municipality'] ?? $addr['county'] ?? $addr['state'] ?? ''));
        $district = trim((string) ($addr['city_district'] ?? $addr['district'] ?? $addr['suburb'] ?? $addr['town'] ?? ''));
        $road = trim((string) ($addr['road'] ?? $addr['pedestrian'] ?? $addr['residential'] ?? ''));
        $house = trim((string) ($addr['house_number'] ?? ''));

        $parts = [];

        if ($city !== '') {
            $parts[] = $city;
        }

        if ($district !== '' && $district !== $city) {
            $parts[] = $district;
        }

        $roadSegment = $road;
        if ($roadSegment !== '' && $house !== '' && ! str_contains($roadSegment, $house)) {
            $roadSegment .= $house;
        } elseif ($roadSegment === '' && $house !== '') {
            $roadSegment = $house;
        }

        if ($roadSegment !== '') {
            $parts[] = $roadSegment;
        }

        if (empty($parts)) {
            return null;
        }

        $composed = implode('', $parts);
        $composed = preg_replace('/[\s\x{00a0}]+/', '', $composed);
        $composed = preg_replace('/,+/', '', $composed);
        $composed = trim($composed, " ,\t\n\r");

        return $composed !== '' ? $composed : null;
    }
}
