<?php

return [
    'base_url' => env('WEATHER_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
    'timeout' => env('WEATHER_TIMEOUT', 10),
];
