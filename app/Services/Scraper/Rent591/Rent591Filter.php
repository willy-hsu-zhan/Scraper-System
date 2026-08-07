<?php

namespace App\Services\Scraper\Rent591;

class Rent591Filter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['title']) && ! empty($item['link']);
    }
}
