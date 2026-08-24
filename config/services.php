<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'line_notify' => [
        'token' => env('LINE_NOTIFY_TOKEN'),
    ],

    'reurl' => [
        'api_key' => env('REURL_API_KEY'),
    ],

    'phone_location' => [
        'secret' => env('PHONE_LOCATION_SECRET'),
    ],

    'telegram' => [
        'apikey' => env('TELEGRAM_BOT_API_KEY', ''),
        'username' => env('TELEGRAM_BOT_USERNAME', ''),
        'phone_location_chat_id' => env('TELEGRAM_PHONE_LOCATION_CHAT_ID', '-5308571034'),
    ],
];
