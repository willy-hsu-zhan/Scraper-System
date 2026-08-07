<?php

namespace App\Services\Scraper\BirthdayDiscount;

use App\Models\BirthdayDiscount;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;

class BirthdayDiscountScraper extends BaseScraper
{
    protected SeleniumService $seleniumService;
    protected BirthdayDiscountParser $parser;
    protected BirthdayDiscountFilter $filter;
    protected BirthdayDiscountFormatter $formatter;

    public function __construct(SeleniumService $seleniumService)
    {
        $this->seleniumService = $seleniumService;
        $this->parser = new BirthdayDiscountParser();
        $this->filter = new BirthdayDiscountFilter();
        $this->formatter = new BirthdayDiscountFormatter();
    }

    public function scrape(): array
    {
        $url = 'https://happybirthday.com.tw/list';
        $webDriver = $this->seleniumService->openWebDriver();
        $items = [];

        try {
            $webDriver->get($url);
            $totalCount = 0;

            try {
                $footerText = $webDriver->findElement(WebDriverBy::cssSelector('.view-footer .text-center'))->getText();
                if (preg_match('/共計\s*(\d+)\s*筆/', $footerText, $matches)) {
                    $totalCount = (int) $matches[1] - 1;
                }
            } catch (\Exception $e) {
            }

            $perPage = 20;
            $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;

            for ($page = 0; $page < $totalPages; $page++) {
                $pageUrl = 'https://happybirthday.com.tw/list?page=' . $page;
                $webDriver->get($pageUrl);
                sleep(2);

                $elements = $webDriver->findElements(WebDriverBy::cssSelector('.col-xs-12.col-sm-8.col-md-8'));

                foreach ($elements as $element) {
                    try {
                        $linkElement = $element->findElement(WebDriverBy::cssSelector('h2 a'));
                        $title = trim($linkElement->getText());
                        $link = 'https://happybirthday.com.tw' . $linkElement->getAttribute('href');
                    } catch (\Exception $e) {
                        continue;
                    }

                    $expireDate = null;
                    try {
                        $finalText = $element->findElement(WebDriverBy::cssSelector('span.final'))->getText();
                        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $finalText, $matches)) {
                            $expireDate = $matches[1];
                        }
                    } catch (\Exception $e) {
                    }

                    $description = $this->safeGetText($element, WebDriverBy::tagName('p'));

                    $items[] = [
                        'title' => $title,
                        'link' => $link,
                        'expire_date' => $expireDate,
                        'description' => $description,
                    ];
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

        $titleHash = BirthdayDiscount::makeTitleHash($item['title']);
        $record = BirthdayDiscount::where('title_hash', $titleHash)->first();

        if ($record) {
            $record->update([
                'expire_date' => $item['expire_date'] ?? null,
                'description' => $item['description'] ?? null,
                'link' => $item['link'] ?? null,
                'is_expired' => false,
            ]);

            return null;
        }

        BirthdayDiscount::create([
            'title' => $item['title'] ?? null,
            'title_hash' => $titleHash,
            'link' => $item['link'] ?? null,
            'expire_date' => $item['expire_date'] ?? null,
            'description' => $item['description'] ?? null,
            'is_expired' => false,
            'is_push' => 0,
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
}
