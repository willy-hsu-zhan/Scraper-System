<?php

namespace App\Services\Scraper\FreeGame;

class FreeGameFormatter
{
    public function format(array $item): string
    {
        $text = '🎮 '.$item['title'].'\n';

        if (! empty($item['description'])) {
            $text .= '📝 '.$item['description'].'\n';
        }

        if (! empty($item['date'])) {
            $text .= '📅 發布：'.$item['date'].'\n';
        }

        $text .= '🔗 '.$item['link'];

        return $text;
    }
}
