<?php

namespace App\Services\Scraper\Ojt;

class OjtFormatter
{
    public function format(array $item): string
    {
        return "📌 {$item['title']}\n📅 {$item['date']}\n🔗 {$item['link']}";
    }
}
