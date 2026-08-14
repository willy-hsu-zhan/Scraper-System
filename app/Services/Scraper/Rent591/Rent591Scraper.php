<?php

namespace App\Services\Scraper\Rent591;

use App\Models\Rent591;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;

class Rent591Scraper extends BaseScraper
{
    protected SeleniumService $seleniumService;

    protected Rent591Parser $parser;

    protected Rent591Filter $filter;

    protected Rent591Formatter $formatter;

    public function __construct(SeleniumService $seleniumService)
    {
        $this->seleniumService = $seleniumService;
        $this->parser = new Rent591Parser();
        $this->filter = new Rent591Filter();
        $this->formatter = new Rent591Formatter();
    }

    public function scrape(): array
    {
        $regions = [
            ['name' => '台北市', 'region' => 1],
            ['name' => '基隆市', 'region' => 2],
            ['name' => '新北市', 'region' => 3],
            ['name' => '新竹市', 'region' => 4],
            ['name' => '新竹縣', 'region' => 5],
            ['name' => '桃園市', 'region' => 6],
            ['name' => '台中市', 'region' => 8],
            ['name' => '台南市', 'region' => 15],
            ['name' => '高雄市', 'region' => 17],
        ];
        $baseUrl = 'https://rent.591.com.tw/list?kind=2&sort=money_asc&price=0_5000';

        $webDriver = $this->seleniumService->openWebDriver();
        $items = [];

        try {
            foreach ($regions as $regionData) {
                $regionName = $regionData['name'];
                $regionCode = $regionData['region'];
                $url = $baseUrl.'&region='.$regionCode;

                $webDriver->get($url);

                $wait = new WebDriverWait($webDriver, 5);
                try {
                    $wait->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(
                            WebDriverBy::cssSelector('.item-info')
                        )
                    );
                } catch (\Exception $e) {
                    continue;
                }

                $rentElements = $webDriver->findElements(WebDriverBy::cssSelector('.item-info'));

                foreach ($rentElements as $rentNode) {
                    try {
                        $titleElement = $rentNode->findElement(WebDriverBy::cssSelector('.item-info-title a'));
                        $priceText = $rentNode
                            ->findElement(WebDriverBy::cssSelector('.item-info-price .inline-flex-row'))
                            ->getText();

                        $link = $titleElement->getAttribute('href');
                        $title = $titleElement->getAttribute('title');
                        $priceInfo = (int) str_replace(',', '', $priceText);

                        $items[] = [
                            'title' => $title,
                            'region' => $regionName,
                            'price_info' => $priceInfo,
                            'link' => $link,
                        ];
                    } catch (\Exception $e) {
                        continue;
                    }
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
        if (empty($item['link']) || Rent591::where('link', $item['link'])->exists()) {
            return null;
        }

        Rent591::create($item);

        return $item;
    }
}
