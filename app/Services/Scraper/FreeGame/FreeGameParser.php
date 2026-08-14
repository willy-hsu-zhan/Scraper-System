<?php

namespace App\Services\Scraper\FreeGame;

use Symfony\Component\DomCrawler\Crawler;

class FreeGameParser
{
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $items = [];

        $crawler->filter('#ssr-news-tag-title')->each(
            function (Crawler $node) use (&$items) {
                $title = trim($node->text());

                if ($title === '') {
                    return;
                }

                $link = $node->filter('a')->count()
                    ? $node->filter('a')->attr('href')
                    : null;

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
