<?php

namespace App\Services\Scraper\BirthdayDiscount;

class BirthdayDiscountFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['title']) && ! empty($item['link']);
    }
}
