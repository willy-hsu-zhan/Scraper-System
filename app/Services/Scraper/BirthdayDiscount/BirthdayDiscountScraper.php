<?php

namespace App\Services\Scraper\BirthdayDiscount;

use App\Models\BirthdayDiscount;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Support\Facades\Log;

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
            $maxPages = 3;

            for ($page = 0; $page < $maxPages; $page++) {
                $pageUrl = $url.'?page='.$page;

                $webDriver->get($pageUrl);

                logger()->info('BirthdayDiscount Debug', [
                    'page' => $page + 1,
                    'url' => $webDriver->getCurrentURL(),
                    'title' => $webDriver->getTitle(),
                    'offer_count' => count(
                        $webDriver->findElements(
                            WebDriverBy::cssSelector('.offer')
                        )
                    ),
                    'html_length' => strlen($webDriver->getPageSource()),
                ]);
                // 等待優惠列表出現
                try {
                    $webDriver->wait(10)->until(
                        function ($driver) {
                            return count(
                                $driver->findElements(
                                    WebDriverBy::cssSelector('.offer')
                                )
                            ) > 0;
                        }
                    );
                } catch (\Exception $e) {
                    // 該頁沒有資料，停止後續頁面
                    break;
                }

                $elements = $webDriver->findElements(
                    WebDriverBy::cssSelector('.offer')
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

                        Log::info('BirthdayDiscount Item Debug', [
                            'title' => $title,
                            'href' => $href,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('BirthdayDiscount Parse Error', [
                            'message' => $e->getMessage(),
                            'html' => $element->getAttribute('outerHTML'),
                        ]);

                        continue;
                    }
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
