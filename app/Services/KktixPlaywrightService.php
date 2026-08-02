<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class KktixPlaywrightService
{
    public function fetchEvent(string $event)
    {
        $response = Http::timeout(120)
            ->get("http://127.0.0.1:3000/scrape/{$event}");

        if (!$response->ok()) {
            return [
                'captured' => [],
                'error' => 'Node service failed',
            ];
        }

        return $response->json();
    }
}
