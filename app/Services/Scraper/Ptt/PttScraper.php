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
        $baseUrl = 'https://www.pttweb.cc/bbs/part-time';

        $jobs = [];

        $webDriver = $this->seleniumService->openWebDriver();

        try {
            $webDriver->get($baseUrl);

            $webDriver->wait(5)->until(function ($driver) {
                return count(
                    $driver->findElements(
                        WebDriverBy::cssSelector('a.e7-article-default')
                    )
                ) > 0;
            });

            $articleElements = $webDriver->findElements(
                WebDriverBy::cssSelector('a.e7-article-default')
            );

            dump('抓到文章：'.count($articleElements));

            foreach ($articleElements as $element) {
                try {
                    $title = $this->parser->extractTitle($element);

                    dump('標題：'.$title);

                    $jobs[] = [
                        'title' => $title,
                        'link' => $this->parser->extractLink($element, $originUrl),
                        'company' => $this->parser->extractAuthor($element),
                        'date' => $this->parser->extractDate($element),
                        'place' => $this->parser->extractPlace($title),
                        'salary' => $this->parser->extractSalary($title),
                    ];
                } catch (\Exception $e) {
                    dump('解析失敗：'.$e->getMessage());
                    continue;
                }
            }
        } finally {
            $webDriver->quit();
        }

        dump('scrape 最後取得：'.count($jobs));

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
