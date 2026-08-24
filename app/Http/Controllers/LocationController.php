<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    public function __construct(
        protected GeocodingService $geocodingService,
        protected WeatherService $weatherService,
        protected TelegramApiClientInterface $telegramApiClient,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('Location API received', [
            'request' => $request->all(),
        ]);

        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'device_name' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $deviceName = (string) $validated['device_name'];
        $weather = null;
        $address = null;

        try {
            $geocodingResult = $this->geocodingService->reverse($latitude, $longitude);
            if (is_array($geocodingResult) && ! empty($geocodingResult['address'])) {
                $address = (string) $geocodingResult['address'];
                Log::info('Reverse geocoding success', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'address' => $address,
                ]);
            } else {
                Log::warning('Geocoding failed', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Geocoding failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $weather = $this->weatherService->getWeather($latitude, $longitude);
            if ($weather === null) {
                Log::warning('Weather API failed', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Weather API failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
        }

        if (is_array($weather)) {
            $chatId = (string) config('services.telegram.phone_location_chat_id', '-5308571034');
            $message = $this->buildTelegramMessage($address, $latitude, $longitude, $deviceName, $weather);

            try {
                $this->telegramApiClient->sendMessage($chatId, $message);
                Log::info('Telegram weather notification sent', [
                    'chat_id' => $chatId,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
            } catch (\Throwable $e) {
                Log::error('Telegram notification failed', [
                    'chat_id' => $chatId,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Location received',
        ]);
    }

    protected function buildTelegramMessage(
        ?string $address,
        float $latitude,
        float $longitude,
        string $deviceName,
        array $weather
    ): string {
        $locationText = $address !== null && $address !== ''
            ? $address
            : '無法取得地址';

        $rainText = $weather['is_raining'] ?? false ? '是' : '否';
        $weatherLabel = (string) ($weather['weather'] ?? '多雲');
        $temperature = (float) ($weather['temperature'] ?? 0);
        $humidity = (int) ($weather['humidity'] ?? 0);
        $precipitationProbability = (int) ($weather['precipitation_probability'] ?? 0);

        $message = "🌤️ 目前天氣資訊\n\n";
        $message .= "📍 位置：\n{$locationText}\n\n";
        $message .= "🌡️ 溫度：{$temperature}°C\n";
        $message .= "💧 濕度：{$humidity}%\n";
        $message .= "🌧️ 降雨機率：{$precipitationProbability}%\n";
        $message .= "☔ 目前降雨：{$rainText}\n";
        $message .= "🌤️ 天氣：{$weatherLabel}\n\n";
        $message .= "📍 座標：\n{$latitude}, {$longitude}\n\n";
        $message .= "📱 裝置：\n{$deviceName}";

        if (($weather['is_raining'] ?? false) === true) {
            $message .= "\n\n⚠️ 目前正在下雨，外出請注意。";
        }

        return $message;
    }
}
