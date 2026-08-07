<?php

namespace App\Services\Scraper\Dcard;

use Symfony\Component\DomCrawler\Crawler;

class DcardParser
{
    public function parse(Crawler $crawler): array
    {
        $items = [];

        $crawler->filter('article')->each(function (Crawler $article) use (&$items) {
            $title = trim($article->filter('h2')->text(''));
            $link = $article->filter('a')->attr('href');

            if (! empty($title) && ! empty($link)) {
                $items[] = [
                    'title' => $title,
                    'link' => 'https://www.dcard.tw'.$link,
                ];
            }
        });

        return $items;
    }
}
