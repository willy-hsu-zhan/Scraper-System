<?php

namespace App\Services\Scraper\Dcard;

class DcardFormatter
{
    public function format(array $item): string
    {
        return "📌 {$item['title']}\n🔗 {$item['link']}";
    }
}
