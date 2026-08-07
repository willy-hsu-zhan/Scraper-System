<?php

namespace App\Services\AI\Contracts;

interface GeminiPromptBuilderInterface
{
    public function buildPdfPrompt(): string;

    public function buildCoursePrompt(string $courseText): string;

    public function buildSummaryPrompt(string $text): string;
}
