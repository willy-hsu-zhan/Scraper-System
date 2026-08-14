<?php

namespace App\Services\Scraper\FreeGame;

use Symfony\Component\DomCrawler\Crawler;

class FreeGameParser
{
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $items = [];

        $crawler->filter('section ul > li')->each(
            function (Crawler $element) use (&$items) {
                $linkElement = $element->filter('a')->first();

                if ($linkElement->count() === 0) {
                    return;
                }

                $href = $linkElement->attr('href');

                $items[] = [
                    'title' => trim(
                        $element->filter('h3')->text('', true)
                    ),
                    'link' => str_starts_with($href, 'http')
                        ? $href
                        : 'https://www.4gamers.com.tw'.$href,
                    'description' => trim(
                        $element->filter('p')->text('', true)
                    ),
                    'date' => $element->filter('time')->attr('datetime'),
                ];
            }
        );

        return $items;
    }
}
