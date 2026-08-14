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

            // 只抓最新 3 頁，避免每次都掃描全部歷史資料
            $maxPages = 3;

            for ($page = 0; $page < $maxPages; $page++) {
                $pageUrl = $url.'?page='.$page;

                try {
                    $webDriver->get($pageUrl);

                    // 等待列表載入
                    $webDriver->wait(10)->until(
                        function ($driver) {
                            return count(
                                $driver->findElements(
                                    WebDriverBy::cssSelector('.col-xs-12.col-sm-8.col-md-8')
                                )
                            ) > 0;
                        }
                    );
                } catch (\Exception $e) {
                    // 該頁載入失敗就停止後續頁面
                    break;
                }

                $elements = $webDriver->findElements(
                    WebDriverBy::cssSelector('.col-xs-12.col-sm-8.col-md-8')
                );

                foreach ($elements as $element) {
                    try {
                        $linkElement = $element->findElement(
                            WebDriverBy::cssSelector('h2 a')
                        );

                        $title = trim($linkElement->getText());
                        $href = $linkElement->getAttribute('href');

                        $link = str_starts_with($href, 'http')
                            ? $href
                            : 'https://happybirthday.com.tw'.$href;
                    } catch (\Exception $e) {
                        continue;
                    }

                    $expireDate = null;

                    try {
                        $finalText = $element
                            ->findElement(WebDriverBy::cssSelector('span.final'))
                            ->getText();

                        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $finalText, $matches)) {
                            $expireDate = $matches[1];
                        }
                    } catch (\Exception $e) {
                        // 沒有期限資料就維持 null
                    }

                    $description = $this->safeGetText(
                        $element,
                        WebDriverBy::tagName('p')
                    );

                    $items[] = [
                        'title' => $title,
                        'link' => $link,
                        'expire_date' => $expireDate,
                        'description' => $description,
                    ];
                }
            }
        } finally {
            try {
                $webDriver->quit();
            } catch (\Exception $e) {
                // Selenium session 已中斷時，不需要再次拋出錯誤
            }
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
