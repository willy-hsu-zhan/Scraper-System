<?php

namespace App\Services\Scraper\JudicialAuction;

class JudicialAuctionFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['title']) && ! empty($item['link']);
    }
}
