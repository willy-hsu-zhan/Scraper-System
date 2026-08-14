<?php

namespace App\Services\Scraper\Ptt;

use App\Models\JobPtt;
use App\Services\Scraper\BaseScraper;
use App\Services\SeleniumService;
use Facebook\WebDriver\WebDriverBy;

class PttScraper extends BaseScraper
{
    protected SeleniumService $seleniumService;

    protected PttParser $parser;

    protected PttFilter $filter;

    protected PttFormatter $formatter;

    public function __construct(SeleniumService $seleniumService)
    {
        $this->seleniumService = $seleniumService;
        $this->parser = new PttParser();
        $this->filter = new PttFilter();
        $this->formatter = new PttFormatter();
    }

    public function scrape(): array
    {
        $originUrl = 'https://www.pttweb.cc';
        $baseUrl = 'https://www.pttweb.cc/bbs/part-time/index.html';
        $jobs = [];

        $webDriver = $this->seleniumService->openWebDriver();

        try {
            $webDriver->get($baseUrl);
            $webDriver->wait(15)->until(function ($driver) {
                return count($driver->findElements(WebDriverBy::cssSelector('a.e7-article-default'))) > 0;
            });

            $maxPage = 2;

            for ($currentPage = 1; $currentPage <= $maxPage; $currentPage++) {
                $articleElements = $webDriver->findElements(WebDriverBy::cssSelector('a.e7-article-default'));

                foreach ($articleElements as $element) {
                    try {
                        $title = $this->parser->extractTitle($element);

                        if ($this->filter($this->cleanItem(['title' => $title]))) {
                            continue;
                        }

                        $jobs[] = [
                            'title' => $title,
                            'link' => $this->parser->extractLink($element, $originUrl),
                            'company' => $this->parser->extractAuthor($element),
                            'date' => $this->parser->extractDate($element),
                            'place' => $this->parser->extractPlace($title),
                            'salary' => $this->parser->extractSalary($title),
                        ];
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                if ($currentPage < $maxPage) {
                    try {
                        $nextPageLink = $webDriver->findElement(WebDriverBy::linkText('‹ 上頁'));
                        $nextPageLink->click();
                        sleep(3);
                    } catch (\Exception $e) {
                        break;
                    }
                }
            }
        } finally {
            $webDriver->quit();
        }

        return $jobs;
    }

    public function filter(array $item): bool
    {
        return $this->filter->shouldSkip($item['title']);
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
        if (JobPtt::where('link', $item['link'])->exists()) {
            return null;
        }

        JobPtt::create($item);

        return $item;
    }

    protected function cleanItem(array $item): array
    {
        $item['title'] = trim($item['title'] ?? '');

        return $item;
    }
}
