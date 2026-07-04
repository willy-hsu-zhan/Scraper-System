<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');
    }

    /**
     * PDF URL → download → summarize
     */
    public function summarizePdfByUrl(string $pdfUrl): ?string
    {
        // 1️⃣ 下載 PDF
        $pdfBinary = Http::timeout(30)
            ->get($pdfUrl)
            ->body();

        if (!$pdfBinary) {
            return null;
        }

        // 2️⃣ base64 encode
        $base64 = base64_encode($pdfBinary);

        $endpoint = "https://generativelanguage.googleapis.com/v1/models/" .
            config('gemini.model') .
            ":generateContent?key={$this->apiKey}";
        $response = Http::post($endpoint, [
            "contents" => [
                [
                    "parts" => [
                        [
                            "text" => "請幫我摘要以下法拍 PDF，整理成條列重點（包含風險與投資價值）："
                        ],
                        [
                            "inline_data" => [
                                "mime_type" => "application/pdf",
                                "data" => $base64
                            ]
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => config('gemini.temperature', 0.2),
                "maxOutputTokens" => config('gemini.max_output_tokens', 2048),
            ]
        ]);

        if (!$response->successful()) {
            return null;
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text');
    }

    public function summarizeText(string $text): ?string
    {
        $endpoint = config('gemini.base_url')
            . "/models/"
            . config('gemini.model')
            . ":generateContent?key={$this->apiKey}";

        $response = Http::post($endpoint, [
            "contents" => [
                [
                    "parts" => [
                        [
                            "text" => "請幫我整理以下法拍案件內容，輸出條列重點（風險 + 投資建議）：\n\n" . $text
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => config('gemini.temperature', 0.2),
                "maxOutputTokens" => config('gemini.max_output_tokens', 1024),
            ]
        ]);
        dd($response->json());
        if (!$response->successful()) {
            return null;
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text');
    }
}
