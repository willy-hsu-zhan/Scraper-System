<?php

namespace App\Services\Scraper\Ojt;

class OjtFormatter
{
    public function format(array $item): string
    {
        $title = $item['title'] ?? $item['course_name'] ?? '未命名課程';
        $date = $item['date'] ?? $item['registration_start_at'] ?? $item['scheduled_start_date'] ?? '';
        $link = $item['link'] ?? $item['training_unit_url'] ?? '';

        return "📌 {$title}\n📅 {$date}\n🔗 {$link}";
    }
}
