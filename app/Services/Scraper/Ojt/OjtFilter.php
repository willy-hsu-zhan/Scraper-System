<?php

namespace App\Services\Scraper\Ojt;

class OjtFilter
{
    public function shouldKeep(array $item): bool
    {
        if (empty($item['course_name']) || empty($item['training_unit_url'])) {
            return false;
        }

        return ! str_contains($item['enrollment_status'] ?? '', '備取');
    }
}
