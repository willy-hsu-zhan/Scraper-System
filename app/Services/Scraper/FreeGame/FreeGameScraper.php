<?php

namespace App\Services\Scraper\FreeGame;

use App\Models\FreeGame4Gamers;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;

class FreeGameScraper extends BaseScraper
{
    protected SeleniumService $seleniumService;
    protected FreeGameParser $parser;
    protected FreeGameFilter $filter;
    protected FreeGameFormatter $formatter;

    public function __construct(SeleniumService $seleniumService)
    {
        $this->seleniumService = $seleniumService;
        $this->parser = new FreeGameParser();
        $this->filter = new FreeGameFilter();
        $this->formatter = new FreeGameFormatter();
    }

    public function scrape(): array
    {
        $url = 'https://www.4gamers.com.tw/news/tag/限時免費';
        $webDriver = $this->seleniumService->openWebDriver();
        $items = [];

        try {
            $webDriver->get($url);
            sleep(3);

            $elements = $webDriver->findElements(WebDriverBy::cssSelector('section ul > li'));

            foreach ($elements as $element) {
                try {
                    $linkElement = $element->findElement(WebDriverBy::cssSelector('a'));
                    $header = $element->findElement(WebDriverBy::cssSelector('h3'));

                    $items[] = [
                        'title' => trim($header->getText()),
                        'link' => 'https://www.4gamers.com.tw'.$linkElement->getAttribute('href'),
                        'description' => $this->safeGetText($element, WebDriverBy::cssSelector('p')),
                        'date' => $this->safeGetAttribute($element, WebDriverBy::cssSelector('time'), 'datetime'),
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }
        } finally {
            $webDriver->quit();
        }

        return $items;
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
        foreach ($items as $item) {
            $this->saveItem($item);
        }
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
            'title' => $item['title'] ?? null,
            'title_hash' => $titleHash,
            'description' => $item['description'] ?? null,
            'link' => $item['link'] ?? null,
            'publish_date' => $item['date'] ?? null,
            'is_push' => false,
        ]);

        return $item;
    }

    protected function safeGetText($element, $by): string
    {
        try {
            return trim($element->findElement($by)->getText());
        } catch (\Exception $e) {
            return '';
        }
    }

    protected function safeGetAttribute($element, $by, string $attribute): ?string
    {
        try {
            return trim($element->findElement($by)->getAttribute($attribute));
        } catch (\Exception $e) {
            return null;
        }
    }
}
