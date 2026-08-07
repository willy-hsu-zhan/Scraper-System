<?php

namespace App\Services\Scraper\Dcard;

class DcardFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['title']) && ! empty($item['link']);
    }
}
