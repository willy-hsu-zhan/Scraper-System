<?php

namespace App\Services\Scraper\FreeGame;

use Symfony\Component\DomCrawler\Crawler;

class FreeGameParser
{
    private const SOURCE_URL = 'https://www.4gamers.com.tw/news/tag/限時免費';

    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $items = [];

        $crawler->filter('#ssr-news-tag-title')->each(
            function (Crawler $node) use (&$items) {
                $title = trim($node->text());

                if (empty($title)) {
                    return;
                }

                $items[] = [
                    'title' => $title,
                    'link' => self::SOURCE_URL,
                    'description' => null,
                    'date' => null,
                ];
            }
        );

        return $items;
    }
}
