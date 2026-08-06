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

        if (! $pdfBinary) {
            return null;
        }

        // 2️⃣ base64 encode
        $base64 = base64_encode($pdfBinary);

        $endpoint = 'https://generativelanguage.googleapis.com/v1/models/'.
            config('gemini.model').
            ":generateContent?key={$this->apiKey}";
        $response = Http::post($endpoint, [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => '你是專業法拍套利投資顧問（台灣市場），請用保守投資角度分析以下法拍 PDF：
輸出必須包含：

- 投資評分（0~100分）
- 是否建議出價（YES / NO / WAIT）
- 風險等級（低 / 中 / 高）
- 3個最關鍵風險點
- 1個最重要機會點
- 建議最高出價區間（如果可判斷）

請避免空泛描述，要偏實戰決策。',
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => 'application/pdf',
                                'data' => $base64,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => config('gemini.temperature', 0.2),
                'maxOutputTokens' => config('gemini.max_output_tokens', 2048),
            ],
        ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text');
    }

    public function summarizeCourseByUrl(string $url): ?string
    {
        // 1. 取得 HTML
        $html = Http::timeout(30)
            ->get($url)
            ->body();

        // 2. 移除 HTML 標籤
        $text = strip_tags($html);

        // 清理空白
        $text = preg_replace('/\s+/', ' ', $text);

        // 3. Gemini
        $endpoint = 'https://generativelanguage.googleapis.com/v1/models/'.
            config('gemini.model').
            ":generateContent?key={$this->apiKey}";

        $response = Http::post($endpoint, [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => "
你是一位專業的課程顧問，請分析以下課程資料。

請不要只做摘要，請從以下角度評估：

【1. 課程介紹】
- 這堂課主要學習什麼？
- 課程核心技能有哪些？
- 理論與實作內容分析。

【2. 師資分析】
請整理講師資訊：
- 講師姓名
- 專業背景
- 相關證照
- 業界經驗
- 是否有公開授課經驗
- 是否有出版、媒體報導或其他公開成就

如果課程資料沒有提供師資資訊，請標示「未提供」。
不要自行猜測。

【3. 學習成果】
請說明完成課程後可以獲得哪些具體技能。

【4. 技能應用】
分析學到的技能可以應用在哪些領域：

- 日常生活用途
- 興趣培養
- 職場工作
- 接案或創業可能性

【5. 費用價值分析】
請整理：

- 課程總費用
- 政府補助金額
- 學員自付金額
- 補助比例

並估算：
如果自行到外部學習類似技能，市場課程價格大約是多少。

注意：
市場價格只能提供合理範圍推估，
若沒有資料請標示「推估」，
不要虛構精確價格。

【6. 適合對象】
請列出：
- 最適合參加的人
- 不適合參加的人

【7. 課程評價】
請最後給出：

推薦程度：
(非常推薦 / 推薦 / 普通 / 不推薦)

原因：
一句話說明。

請使用繁體中文回答。

以下是課程資料：

{$text}
",
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 2048,
            ],
        ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get(
            $response->json(),
            'candidates.0.content.parts.0.text'
        );
    }

    public function summarizeText(string $text): ?string
    {
        $endpoint = config('gemini.base_url')
            .'/models/'
            .config('gemini.model')
            .":generateContent?key={$this->apiKey}";

        $response = Http::post($endpoint, [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => "請幫我整理以下法拍案件內容，輸出條列重點（風險 + 投資建議）：\n\n".$text,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => config('gemini.temperature', 0.2),
                'maxOutputTokens' => config('gemini.max_output_tokens', 1024),
            ],
        ]);

        if (! $response->successful()) {
            return null;
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text');
    }
}
