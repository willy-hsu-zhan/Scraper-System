<?php

namespace App\Services\Scraper\FreeGame;

use Symfony\Component\DomCrawler\Crawler;

class FreeGameParser
{
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $items = [];

        $crawler->filter('a[href*="/news/detail/"]')->each(
            function (Crawler $node) use (&$items) {
                $title = trim($node->filter('h3')->text(''));
                $link = $node->attr('href');

                if (empty($title) || empty($link)) {
                    return;
                }

                if (! str_starts_with($link, 'http')) {
                    $link = 'https://www.4gamers.com.tw'.$link;
                }

                $items[] = [
                    'title' => $title,
                    'link' => $link,
                    'description' => null,
                    'date' => null,
                ];
            }
        );

        return $items;
    }
}
