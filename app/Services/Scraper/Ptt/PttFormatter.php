<?php

namespace App\Services\Scraper\Ptt;

class PttFormatter
{
    public function format(array $job): string
    {
        return <<<TEXT
📌 {$job['title']}
👤 {$job['company']}
📅 {$job['date']}
🔗 {$job['link']}

──────────────
TEXT;
    }
}
