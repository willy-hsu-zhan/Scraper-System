<?php

namespace App\Services\Scraper\Dcard;

use App\Services\ProxyService;
use App\Services\Scraper\BaseScraper;
use Goutte\Client;
use Illuminate\Support\Facades\Http;

class DcardScraper extends BaseScraper
{
    protected ProxyService $proxyService;

    protected DcardParser $parser;

    protected DcardFilter $filter;

    protected DcardFormatter $formatter;

    public function __construct(ProxyService $proxyService)
    {
        $this->proxyService = $proxyService;
        $this->parser = new DcardParser();
        $this->filter = new DcardFilter();
        $this->formatter = new DcardFormatter();
    }

    public function scrape(): array
    {
        $proxy = $this->proxyService->getRamdomProxy();
        $response = Http::withOptions([
            'proxy' => $proxy,
            'timeout' => 6000,
            'connect_timeout' => 600,
        ])->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ])->get('https://www.dcard.tw/f/food?tab=latest');

        $content = $response->body();
        $client = new Client();
        $crawler = $client->request('GET', 'data:text/html,'.urlencode($content));

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
        // Dcard items are not persisted by default.
    }

    protected function saveItem(array $item): ?array
    {
        return $item;
    }
}
