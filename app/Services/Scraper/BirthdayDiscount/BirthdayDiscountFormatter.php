<?php

namespace App\Services\Scraper\BirthdayDiscount;

class BirthdayDiscountFormatter
{
    public function format(array $item): string
    {
        $text = '🎂 '.$item['title'].'\n';

        if (! empty($item['description'])) {
            $text .= '📝 '.$item['description'].'\n';
        }

        if (! empty($item['expire_date'])) {
            $text .= '⏰ 截止：'.$item['expire_date'].'\n';
        }

        $text .= '🔗 '.$item['link'];

        return $text;
    }
}
