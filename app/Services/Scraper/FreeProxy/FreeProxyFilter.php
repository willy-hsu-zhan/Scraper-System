<?php

namespace App\Services\Scraper\FreeProxy;

class FreeProxyFilter
{
    public function shouldKeep(array $item): bool
    {
        return ! empty($item['ip']) && ! empty($item['port']);
    }
}
