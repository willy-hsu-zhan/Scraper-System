<?php

namespace App\Services\Scraper\FreeGame;

class FreeGameFormatter
{
    public function format(array $item): string
    {
        return "🎮 {$item['title']}\n"
            ."🔗 {$item['link']}";
    }
}
