<?php

namespace App\Services\Scraper\Ptt;

class PttFilter
{
    public function shouldSkip(string $title): bool
    {
        $skipKeywords = [
            '[公告]',
            'Fw:',
            'FW:',
            'Re:',
            '板規',
            '置底',
            '版主',
            '問答',
        ];

        foreach ($skipKeywords as $keyword) {
            if (mb_strpos($title, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
