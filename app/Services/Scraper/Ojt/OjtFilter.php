<?php

namespace App\Services\Scraper\Ojt;

class OjtFilter
{
    public function shouldKeep(array $item): bool
    {
        $result = ! empty($item['course_name'])
            && ! empty($item['training_unit_url']);

        logger()->info('Ojt Filter', [
            'course_name' => $item['course_name'] ?? null,
            'training_unit_url' => $item['training_unit_url'] ?? null,
            'result' => $result,
        ]);

        return $result;
    }
}
