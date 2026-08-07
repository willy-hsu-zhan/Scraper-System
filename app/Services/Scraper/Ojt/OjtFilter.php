<?php

namespace App\Services\Scraper\Ojt;

class OjtFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['title']) && ! empty($item['link']);
    }
}
