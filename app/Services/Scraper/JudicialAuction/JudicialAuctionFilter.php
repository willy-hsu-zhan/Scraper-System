<?php

namespace App\Services\Scraper\JudicialAuction;

class JudicialAuctionFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['court']) && ! empty($item['case_no']);
    }
}
