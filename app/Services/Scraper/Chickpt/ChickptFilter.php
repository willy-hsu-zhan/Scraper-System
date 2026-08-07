<?php

namespace App\Services\Scraper\Chickpt;

class ChickptFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['title']) && ! empty($item['link']);
    }
}
