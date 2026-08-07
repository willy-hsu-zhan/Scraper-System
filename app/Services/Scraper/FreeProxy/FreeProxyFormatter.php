<?php

namespace App\Services\Scraper\FreeProxy;

class FreeProxyFormatter
{
    public function format(array $item): string
    {
        return sprintf('http://%s:%s', $item['ip'] ?? '', $item['port'] ?? '');
    }
}
