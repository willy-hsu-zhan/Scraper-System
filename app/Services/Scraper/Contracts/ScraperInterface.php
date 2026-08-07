<?php

namespace App\Services\Scraper\Contracts;

interface ScraperInterface
{
    public function run(): array;

    public function scrape(): array;

    public function filter(array $item): bool;

    public function format(array $item): string;

    public function save(array $items): void;
}
