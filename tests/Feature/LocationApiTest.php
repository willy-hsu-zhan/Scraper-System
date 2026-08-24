<?php

namespace Tests\Feature;

use App\Services\GeocodingService;
use App\Services\Telegram\Contracts\TelegramApiClientInterface;
use App\Services\WeatherService;
use Mockery;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    public function test_location_api_returns_success_and_sends_weather_notification(): void
    {
        config()->set('services.phone_location.secret', 'test-secret');

        $this->instance(GeocodingService::class, Mockery::mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('reverse')
                ->once()
                ->with(25.078123, 121.56789)
                ->andReturn([
                    'address' => '臺北市內湖區文湖街7號',
                    'display_name' => '臺北市內湖區文湖街7號',
                    'raw' => [],
                ]);
        }));

        $this->instance(WeatherService::class, Mockery::mock(WeatherService::class, function ($mock) {
            $mock->shouldReceive('getWeather')
                ->once()
                ->with(25.078123, 121.56789)
                ->andReturn([
                    'temperature' => 31.0,
                    'humidity' => 72,
                    'precipitation_probability' => 60,
                    'is_raining' => false,
                    'weather' => '多雲',
                ]);
        }));

        $this->instance(TelegramApiClientInterface::class, Mockery::mock(TelegramApiClientInterface::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(
                    '-5308571034',
                    Mockery::on(function (string $message) {
                        return str_contains($message, '臺北市內湖區文湖街7號')
                            && str_contains($message, '31')
                            && str_contains($message, '25.078123')
                            && str_contains($message, '121.56789');
                    })
                )
                ->andReturn('ok');
        }));

        $response = $this->withHeader('Authorization', 'Bearer test-secret')->postJson('/api/location', [
            'device_id' => 'xxx',
            'device_name' => 'Samsung S23',
            'latitude' => 25.078123,
            'longitude' => 121.56789,
            'accuracy' => 10.5,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Location received',
            ]);
    }
}
