<?php

namespace App\Services;

use Goutte\Client;

class ProxyService
{
    public function __construct()
    {

    }

    public function getRamdomProxy()
    {
        $client = new Client();

        $url = 'https://www.us-proxy.org/';

        $crawler = $client->request('GET', $url);

        $proxies = $crawler->filter('table.table tbody tr')->each(function ($row) {
            $columns = $row->filter('td')->each(function ($col) {
                return $col->text();
            });

            return $columns; // return td屬性
        });

        foreach ($proxies as $index => $proxy) {
            if (! isset($proxy[6])) {
                unset($proxies[$index]);

                continue;
            }

            if (is_numeric($proxy[6])) {
                unset($proxies[$index]);
            }
        }

        $proxies = array_values($proxies);

        $ramdomProxy = $proxies[array_rand($proxies)];

        $url = 'http://'.$ramdomProxy[0].':'.$ramdomProxy[1];

        return $url;
    }
}
