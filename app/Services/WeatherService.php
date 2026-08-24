<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = (string) config('weather.base_url', 'https://api.open-meteo.com/v1/forecast');
        $this->timeout = (int) config('weather.timeout', 10);
    }

    public function getWeather(float $latitude, float $longitude): ?array
    {
        Log::info('Weather API request', [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        try {
            $response = Http::timeout($this->timeout)->get($this->baseUrl, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,relative_humidity_2m,precipitation,rain,weather_code',
                'hourly' => 'precipitation_probability',
                'timezone' => 'auto',
                'forecast_days' => 1,
            ]);

            if (! $response->successful()) {
                Log::error('Weather API failed', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $current = $data['current'] ?? null;

            if (! is_array($current)) {
                Log::error('Weather API failed', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'message' => 'Missing current weather payload.',
                    'payload' => $data,
                ]);

                return null;
            }

            $rain = (float) ($current['rain'] ?? 0);
            $precipitation = (float) ($current['precipitation'] ?? 0);
            $weatherCode = (int) ($current['weather_code'] ?? 0);
            $probability = $this->resolvePrecipitationProbability($data);
            $isRaining = $rain > 0 || $precipitation > 0;

            $weather = [
                'temperature' => (float) ($current['temperature_2m'] ?? 0),
                'humidity' => (int) ($current['relative_humidity_2m'] ?? 0),
                'precipitation_probability' => (int) round($probability),
                'is_raining' => $isRaining,
                'weather' => $this->mapWeatherCodeToChinese($weatherCode),
            ];

            Log::info('Weather API success', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'weather' => $weather,
            ]);

            return $weather;
        } catch (\Throwable $e) {
            Log::error('Weather API failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function resolvePrecipitationProbability(array $data): float
    {
        $hourly = $data['hourly'] ?? [];
        $times = $hourly['time'] ?? [];
        $probabilities = $hourly['precipitation_probability'] ?? [];

        if (empty($probabilities)) {
            return 0.0;
        }

        $currentTime = $data['current']['time'] ?? null;

        if (is_string($currentTime) && is_array($times)) {
            $index = array_search($currentTime, $times, true);
            if ($index !== false && isset($probabilities[$index])) {
                return (float) $probabilities[$index];
            }
        }

        return (float) ($probabilities[0] ?? 0);
    }

    protected function mapWeatherCodeToChinese(int $weatherCode): string
    {
        $mapping = [
            0 => '晴朗',
            1 => '多雲',
            2 => '多雲',
            3 => '多雲',
            45 => '有霧',
            48 => '有霧',
            51 => '毛毛雨',
            53 => '毛毛雨',
            55 => '毛毛雨',
            56 => '毛毛雨',
            57 => '毛毛雨',
            61 => '降雨',
            63 => '降雨',
            65 => '降雨',
            66 => '降雨',
            67 => '降雨',
            71 => '降雪',
            73 => '降雪',
            75 => '降雪',
            77 => '降雪',
            80 => '短暫陣雨',
            81 => '短暫陣雨',
            82 => '短暫陣雨',
            85 => '降雪',
            86 => '降雪',
            95 => '雷雨',
            96 => '雷雨',
            99 => '雷雨',
        ];

        if (isset($mapping[$weatherCode])) {
            return $mapping[$weatherCode];
        }

        return '多雲';
    }
}
