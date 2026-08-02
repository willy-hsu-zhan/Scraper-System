<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestReurlShorten extends Command
{
    protected $signature = 'test:reurl-shorten';

    protected $description = 'Test Reurl shorten API';

    public function handle()
    {
        $url = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=%E7%BE%8E%E5%AE%B9%E9%A1%A7%E5%AE%A2%E7%B6%93%E7%87%9F%E8%88%87%E6%95%B8%E4%BD%8D%E5%B7%A5%E5%85%B7%E6%87%89%E7%94%A8%E5%AF%A6%E5%8B%99%E7%8F%AD%E7%AC%AC01%E6%9C%9F+%28%E7%A4%BE%E5%9C%98%E6%B3%95%E4%BA%BA%E4%B8%AD%E8%8F%AF%E5%81%A5%E5%BA%B7%E8%AA%BF%E7%90%86%E7%BE%8E%E5%AE%B9%E9%A4%8A%E7%94%9F%E5%8D%94%E6%9C%83%29&dates=20260718T040000Z/20260814T100000Z&details=%E5%A0%B1%E5%90%8D%E7%B6%B2%E5%9D%80%EF%BC%9Ahttps%3A%2F%2Fojt.wda.gov.tw%2FClassSearch%2FDetail%3FPlanType%3D1%26OCID%3D173074&location=%28%E5%AD%B8%29+%E6%96%B0%E5%8C%97%E5%B8%82+%28%E8%A1%93%29+%E6%96%B0%E5%8C%97%E5%B8%82+%28%E5%AF%A6%E9%AB%94%E8%AA%B2%E7%A8%8B%29&sf=true&output=xml';

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'reurl-api-key' => config('services.reurl.api_key'),
        ])->post('https://api.reurl.cc/shorten', [
            'url' => $url,
            'utm_source' => 'LINE',
            'utm_medium' => 'notification',
        ]);

        $this->info('HTTP Status: ' . $response->status());

        $this->line(
            json_encode(
                $response->json(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );

        return Command::SUCCESS;
    }
}
