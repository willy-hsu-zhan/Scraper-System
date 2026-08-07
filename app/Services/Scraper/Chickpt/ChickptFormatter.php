<?php

namespace App\Services\Scraper\Chickpt;

class ChickptFormatter
{
    public function format(array $item): string
    {
        return "📌 {$item['title']}\n🏢 {$item['company']}\n🔗 {$item['link']}";
    }
}
