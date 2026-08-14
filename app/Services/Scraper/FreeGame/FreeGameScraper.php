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

            logger()->info('FreeGame Debug', [
                'url' => $webDriver->getCurrentURL(),
                'title' => $webDriver->getTitle(),
                'html_length' => strlen($webDriver->getPageSource()),
            ]);

            $elements = $webDriver->findElements(
                WebDriverBy::cssSelector('section ul > li')
            );

            logger()->info('FreeGame Elements', [
                'count' => count($elements),
            ]);

            foreach ($elements as $index => $element) {
                try {
                    $linkElement = $element->findElement(
                        WebDriverBy::cssSelector('a')
                    );

                    $header = $element->findElement(
                        WebDriverBy::cssSelector('h3')
                    );

                    $title = trim($header->getText());

                    $href = $linkElement->getAttribute('href');

                    $items[] = [
                        'title' => $title,
                        'link' => str_starts_with($href, 'http')
                            ? $href
                            : 'https://www.4gamers.com.tw'.$href,
                        'description' => $this->safeGetText(
                            $element,
                            WebDriverBy::cssSelector('p')
                        ),
                        'date' => $this->safeGetAttribute(
                            $element,
                            WebDriverBy::cssSelector('time'),
                            'datetime'
                        ),
                    ];

                    logger()->info('FreeGame Item', [
                        'index' => $index,
                        'title' => $title,
                        'href' => $href,
                    ]);
                } catch (\Exception $e) {
                    logger()->error('FreeGame Parse Error', [
                        'index' => $index,
                        'message' => $e->getMessage(),
                        'html' => $element->getAttribute('outerHTML'),
                    ]);

                    continue;
                }
            }
        } finally {
            try {
                $webDriver->quit();
            } catch (\Exception $e) {
                // Selenium session 已中斷時，不需要再次拋出錯誤
            }
        }

        logger()->info('FreeGame Result', [
            'count' => count($items),
        ]);

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
