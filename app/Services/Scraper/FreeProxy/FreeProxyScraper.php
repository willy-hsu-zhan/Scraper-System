<?php

namespace App\Services\Scraper\FreeProxy;

use App\Services\Scraper\BaseScraper;
use Goutte\Client;

class FreeProxyScraper extends BaseScraper
{
    protected FreeProxyParser $parser;
    protected FreeProxyFilter $filter;
    protected FreeProxyFormatter $formatter;

    public function __construct()
    {
        $this->parser = new FreeProxyParser();
        $this->filter = new FreeProxyFilter();
        $this->formatter = new FreeProxyFormatter();
    }

    public function scrape(): array
    {
        $client = new Client();
        $crawler = $client->request('GET', 'https://free-proxy-list.net/');

        return $this->parser->parse($crawler);
    }

    public function filter(array $item): bool
    {
        return $this->filter->shouldKeep($item);
    }

    public function format(array $item): string
    {
        return $this->formatter->format($item);
    }

    public function save(array $items): void
    {
        // FreeProxy scraper does not persist proxies by default.
    }

    protected function saveItem(array $item): ?array
    {
        return $item;
    }
}
