<?php

namespace App\Services\Scraper\JudicialAuction;

class JudicialAuctionFormatter
{
    public function format(array $item): string
    {
        return "📌 {$item['title']}\n🏛 {$item['court']}\n🔗 {$item['link']}";
    }
}
