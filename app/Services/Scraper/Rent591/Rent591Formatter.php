<?php

namespace App\Services\Scraper\Rent591;

class Rent591Formatter
{
    public function format(array $item): string
    {
        return "🏠 {$item['title']}\n📍 {$item['region']}\n💰 {$item['price']}\n🔗 {$item['link']}";
    }
}
