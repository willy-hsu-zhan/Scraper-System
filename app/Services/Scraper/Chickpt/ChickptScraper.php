<?php

namespace App\Services\Scraper\Chickpt;

use App\Models\JobChickpt;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class ChickptScraper extends BaseScraper
{
    protected SeleniumService $seleniumService;

    protected ChickptParser $parser;

    protected ChickptFilter $filter;

    protected ChickptFormatter $formatter;

    public function __construct(SeleniumService $seleniumService)
    {
        $this->seleniumService = $seleniumService;
        $this->parser = new ChickptParser();
        $this->filter = new ChickptFilter();
        $this->formatter = new ChickptFormatter();
    }

    public function scrape(): array
    {
        $baseUrl = 'https://www.chickpt.com.tw';
        $url = 'https://www.chickpt.com.tw/cases?is_address_no_limit=1';
        $webDriver = $this->seleniumService->openWebDriver();
        $jobs = [];

        try {
            $webDriver->get($url);
            $currentPage = 1;

            while ($currentPage <= 3) {
                try {
                    $pageLink = $webDriver->findElement(WebDriverBy::linkText((string) $currentPage));
                    $pageLink->click();

                    $webDriver->wait(10)->until(
                        WebDriverExpectedCondition::visibilityOfElementLocated(
                            WebDriverBy::linkText((string) $currentPage)
                        )
                    );
                } catch (\Exception $e) {
                    break;
                }

                $jobElements = $webDriver->findElements(WebDriverBy::cssSelector('.jobs-wrap ul#job-list.job-list.show li'));

                foreach ($jobElements as $jobNode) {
                    try {
                        $title = trim($jobNode->findElement(WebDriverBy::cssSelector('h2'))->getText());
                        $relativeLink = $jobNode->findElement(WebDriverBy::cssSelector('a'))->getAttribute('href');
                        $link = str_starts_with($relativeLink, 'http') ? $relativeLink : $baseUrl.$relativeLink;
                        $company = trim($jobNode->findElement(WebDriverBy::cssSelector('.ellipsis-job-company'))->getText());
                        $salary = trim($jobNode->findElement(WebDriverBy::cssSelector('.salary'))->getText());
                        $place = trim($jobNode->findElement(WebDriverBy::cssSelector('.place'))->getText());

                        $jobs[] = [
                            'title' => $title,
                            'link' => $link,
                            'company' => $company,
                            'salary' => $salary,
                            'place' => $place,
                        ];
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                $currentPage++;
            }
        } finally {
            $webDriver->quit();
        }

        return $jobs;
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
        if (empty($item['link']) || JobChickpt::where('link', $item['link'])->exists()) {
            return null;
        }

        JobChickpt::create([
            'title' => $item['title'] ?? null,
            'link' => $item['link'] ?? null,
            'company' => $item['company'] ?? null,
            'salary' => $item['salary'] ?? null,
            'place' => $item['place'] ?? null,
        ]);

        return $item;
    }
}
