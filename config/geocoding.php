<?php

return [
    // Nominatim base URL (no API key required)
    'nominatim_url' => env('GEOCODING_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),

    // User-Agent to comply with Nominatim usage policy
    'user_agent' => env('GEOCODING_USER_AGENT', 'Scraper-System Telegram Bot'),

    // Timeout seconds for HTTP requests
    'timeout' => env('GEOCODING_TIMEOUT', 10),
];
