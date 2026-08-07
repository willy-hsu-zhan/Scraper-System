<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\GeminiPromptBuilderInterface;

class GeminiPromptBuilder implements GeminiPromptBuilderInterface
{
    public function buildPdfPrompt(): string
    {
        return "你是專業法拍套利投資顧問（台灣市場），請用保守投資角度分析以下法拍 PDF：\n"
            ."輸出必須包含：\n\n"
            ."- 投資評分（0~100分）\n"
            ."- 是否建議出價（YES / NO / WAIT）\n"
            ."- 風險等級（低 / 中 / 高）\n"
            ."- 3個最關鍵風險點\n"
            ."- 1個最重要機會點\n"
            ."- 建議最高出價區間（如果可判斷）\n\n"
            ."請避免空泛描述，要偏實戰決策。";
    }

    public function buildCoursePrompt(string $courseText): string
    {
        return "你是一位專業的課程顧問，請分析以下課程資料。\n\n"
            ."請不要只做摘要，請從以下角度評估：\n\n"
            ."【1. 課程介紹】\n"
            ."- 這堂課主要學習什麼？\n"
            ."- 課程核心技能有哪些？\n"
            ."- 理論與實作內容分析。\n\n"
            ."【2. 師資分析】\n"
            ."請整理講師資訊：\n"
            ."- 講師姓名\n"
            ."- 專業背景\n"
            ."- 相關證照\n"
            ."- 業界經驗\n"
            ."- 是否有公開授課經驗\n"
            ."- 是否有出版、媒體報導或其他公開成就\n\n"
            ."如果課程資料沒有提供師資資訊，請標示「未提供」。\n"
            ."不要自行猜測。\n\n"
            ."【3. 學習成果】\n"
            ."請說明完成課程後可以獲得哪些具體技能。\n\n"
            ."【4. 技能應用】\n"
            ."分析學到的技能可以應用在哪些領域：\n"
            ."- 日常生活用途\n"
            ."- 興趣培養\n"
            ."- 職場工作\n"
            ."- 接案或創業可能性\n\n"
            ."【5. 費用價值分析】\n"
            ."請整理：\n"
            ."- 課程總費用\n"
            ."- 政府補助金額\n"
            ."- 學員自付金額\n"
            ."- 補助比例\n\n"
            ."並估算：\n"
            ."如果自行到外部學習類似技能，市場課程價格大約是多少。\n\n"
            ."注意：\n"
            ."市場價格只能提供合理範圍推估，\n"
            ."若沒有資料請標示「推估」，\n"
            ."不要虛構精確價格。\n\n"
            ."【6. 適合對象】\n"
            ."請列出：\n"
            ."- 最適合參加的人\n"
            ."- 不適合參加的人\n\n"
            ."【7. 課程評價】\n"
            ."請最後給出：\n"
            ."推薦程度：\n"
            ."(非常推薦 / 推薦 / 普通 / 不推薦)\n"
            ."原因：\n"
            ."一句話說明。\n\n"
            ."請使用繁體中文回答。\n\n"
            ."以下是課程資料：\n\n{$courseText}";
    }

    public function buildSummaryPrompt(string $text): string
    {
        return "請幫我整理以下法拍案件內容，輸出條列重點（風險 + 投資建議）：\n\n{$text}";
    }
}
