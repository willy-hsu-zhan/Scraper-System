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

                $items[] = [
                    'title' => $title,
                    'link' => '',
                    'description' => '',
                    'date' => null,
                ];
            }
        );

        return $items;
    }
}
