<?php

namespace App\Services\Scraper\DeliveryCoupon;

class DeliveryCouponFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['content']) && ! empty($item['link']);
    }
}
