<?php

namespace App\Services\Scraper;

use App\Services\Scraper\Contracts\ScraperInterface;

abstract class BaseScraper implements ScraperInterface
{
    protected array $items = [];

    public function run(): array
    {
        $scrapedItems = $this->scrape();

        $newItems = [];

        foreach ($scrapedItems as $item) {
            if (! $this->filter($item)) {
                continue;
            }

            $newItem = $this->saveItem($item);

            if ($newItem !== null) {
                $newItems[] = $newItem;
            }
        }

        return $newItems;
    }

    abstract public function scrape(): array;

    abstract public function filter(array $item): bool;

    abstract public function format(array $item): string;

    public function save(array $items): void
    {
        foreach ($items as $item) {
            $this->saveItem($item);
        }
    }

    /**
     * Save a single item and return the persisted item if it is new.
     */
    abstract protected function saveItem(array $item): ?array;
}
