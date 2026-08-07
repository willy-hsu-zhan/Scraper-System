<?php

namespace App\Services\Scraper\DeliveryCoupon;

use App\Models\DeliveryCoupon;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;

class DeliveryCouponScraper extends BaseScraper
{
    protected SeleniumService $seleniumService;
    protected DeliveryCouponParser $parser;
    protected DeliveryCouponFilter $filter;
    protected DeliveryCouponFormatter $formatter;

    public function __construct(SeleniumService $seleniumService)
    {
        $this->seleniumService = $seleniumService;
        $this->parser = new DeliveryCouponParser();
        $this->filter = new DeliveryCouponFilter();
        $this->formatter = new DeliveryCouponFormatter();
    }

    public function scrape(): array
    {
        $url = 'https://www.callingtaiwan.com.tw/%E5%A4%96%E9%80%81%E5%84%AA%E6%83%A0%E7%B8%BD%E6%95%B4%E7%90%86-foodpanda-ubereats/';
        $webDriver = $this->seleniumService->openWebDriver();
        $coupons = [];

        try {
            $webDriver->get($url);
            sleep(10);

            $headers = $webDriver->findElements(WebDriverBy::cssSelector('.entry-content h2'));
            $targetKeyword = '首購優惠碼/折扣碼/信用卡優惠';

            foreach ($headers as $header) {
                $sectionTitle = trim($header->getText());

                if (mb_strpos($sectionTitle, $targetKeyword) === false) {
                    continue;
                }

                try {
                    $table = $header->findElement(WebDriverBy::xpath('following::table[1]'));
                } catch (\Exception $e) {
                    continue;
                }

                $rows = $table->findElements(WebDriverBy::cssSelector('tbody tr'));

                foreach ($rows as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }

                    $cols = $row->findElements(WebDriverBy::tagName('td'));

                    if (count($cols) < 3) {
                        continue;
                    }

                    try {
                        $linkElement = $cols[2]->findElement(WebDriverBy::tagName('a'));
                    } catch (\Exception $e) {
                        continue;
                    }

                    $expireDate = trim($cols[0]->getText());
                    $content = trim($cols[1]->getText());
                    $code = trim($linkElement->getText());
                    $link = trim($linkElement->getAttribute('href'));
                    $platform = DeliveryCoupon::detectPlatform($sectionTitle);

                    if ($this->isExpired($expireDate)) {
                        continue;
                    }

                    $coupons[] = [
                        'section_title' => $sectionTitle,
                        'platform' => $platform,
                        'expire_date' => $expireDate,
                        'content' => $content,
                        'code' => $code,
                        'link' => $link,
                        'content_hash' => hash('sha256', $sectionTitle . '|' . $content),
                    ];
                }
            }
        } finally {
            $webDriver->quit();
        }

        return $coupons;
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
        $contentHash = $item['content_hash'] ?? null;

        if (empty($contentHash) || DeliveryCoupon::where('content_hash', $contentHash)->exists()) {
            return null;
        }

        DeliveryCoupon::create([
            'section_title' => $item['section_title'] ?? null,
            'platform' => $item['platform'] ?? null,
            'expire_date' => $item['expire_date'] ?? null,
            'content' => $item['content'] ?? null,
            'code' => $item['code'] ?? null,
            'link' => $item['link'] ?? null,
            'content_hash' => $contentHash,
        ]);

        return $item;
    }

    protected function isExpired(?string $expireDate): bool
    {
        if (empty($expireDate)) {
            return false;
        }

        if (mb_strpos($expireDate, '兌完') !== false) {
            return false;
        }

        try {
            $date = \Illuminate\Support\Carbon::createFromFormat('Y/m/d', trim($expireDate));
        } catch (\Exception $e) {
            return false;
        }

        return $date->lt(\Illuminate\Support\Carbon::today());
    }
}
