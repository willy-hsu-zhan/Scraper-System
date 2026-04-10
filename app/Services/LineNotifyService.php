<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LineNotifyService
{
    protected $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function sendMessage($message)
    {
        $url = 'https://notify-api.line.me/api/notify';

        $response = Http::withToken($this->token)->asForm()->post($url, ['message' => $message,]);

        return $response->getBody()->getContents();
    }
}
