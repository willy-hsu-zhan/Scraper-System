<?php

namespace App\Services\Scraper\FreeGame;

use App\Models\FreeGame4Gamers;
use App\Services\Scraper\BaseScraper;
use Illuminate\Support\Facades\Http;

class FreeGameScraper extends BaseScraper
{
    protected FreeGameParser $parser;

    protected FreeGameFilter $filter;

    protected FreeGameFormatter $formatter;

    public function __construct()
    {
        $this->parser = new FreeGameParser();
        $this->filter = new FreeGameFilter();
        $this->formatter = new FreeGameFormatter();
    }

    public function scrape(): array
    {
        $url = 'https://www.4gamers.com.tw/news/tag/限時免費';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get($url);

            logger()->info('FreeGame Request', [
                'status' => $response->status(),
                'url' => $url,
                'html_length' => strlen($response->body()),
            ]);

            if (! $response->successful()) {
                logger()->error('FreeGame Request Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $items = $this->parser->parse($response->body());

            logger()->info('FreeGame Result', [
                'count' => count($items),
            ]);

            return $items;
        } catch (\Throwable $e) {
            logger()->error('FreeGame Scrape Error', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function filter(array $item): bool
    {
        return $this->filter->shouldKeep($item);
    }

    public function format(array $item): string
    {
        return $this->formatter->format($item);
    }

    protected function saveItem(array $item): ?array
    {
        if (empty($item['title'])) {
            return null;
        }

        $titleHash = FreeGame4Gamers::makeTitleHash($item['title']);

        $record = FreeGame4Gamers::where('title_hash', $titleHash)->first();

        if ($record) {
            $record->update([
                'description' => $item['description'] ?? null,
                'link' => $item['link'] ?? null,
                'publish_date' => $item['date'] ?? null,
            ]);

            return null;
        }

        FreeGame4Gamers::create([
            'title' => $item['title'],
            'title_hash' => $titleHash,
            'description' => $item['description'] ?? null,
            'link' => $item['link'] ?? null,
            'publish_date' => $item['date'] ?? null,
            'is_push' => false,
        ]);

        return $item;
    }
}
