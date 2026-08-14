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

                if (empty($title)) {
                    return;
                }

                $link = null;

                try {
                    $link = $node
                        ->closest('a')
                        ->attr('href');
                } catch (\Throwable $e) {
                    // 找不到連結時略過
                }

                if ($link && ! str_starts_with($link, 'http')) {
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
