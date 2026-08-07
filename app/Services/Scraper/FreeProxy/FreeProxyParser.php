<?php

namespace App\Services\Scraper\FreeProxy;

use Symfony\Component\DomCrawler\Crawler;

class FreeProxyParser
{
    public function parse(Crawler $crawler): array
    {
        $proxies = [];

        $crawler->filter('table.table tbody tr')->each(function (Crawler $row) use (&$proxies) {
            $columns = $row->filter('td')->each(function (Crawler $col) {
                return trim($col->text());
            });

            if (count($columns) >= 2 && ! is_numeric($columns[6] ?? null)) {
                $proxies[] = [
                    'ip' => $columns[0] ?? null,
                    'port' => $columns[1] ?? null,
                    'https' => $columns[6] ?? null,
                ];
            }
        });

        return $proxies;
    }
}
