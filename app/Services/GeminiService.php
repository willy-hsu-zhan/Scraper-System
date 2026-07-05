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
                            "text" => "你是專業法拍套利投資顧問（台灣市場），請用保守投資角度分析以下法拍 PDF：
輸出必須包含：

- 投資評分（0~100分）
- 是否建議出價（YES / NO / WAIT）
- 風險等級（低 / 中 / 高）
- 3個最關鍵風險點
- 1個最重要機會點
- 建議最高出價區間（如果可判斷）

請避免空泛描述，要偏實戰決策。"
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

        if (!$response->successful()) {
            return null;
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text');
    }
}
