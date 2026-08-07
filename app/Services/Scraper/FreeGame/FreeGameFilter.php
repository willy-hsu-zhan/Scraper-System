<?php

namespace App\Services\Scraper\FreeGame;

class FreeGameFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['title']) && ! empty($item['link']);
    }
}
