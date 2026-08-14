<?php

return [

    /**
     * Gemini API Key
     */
    'api_key' => env('GEMINI_API_KEY', ''),

    /**
     * 預設模型
     * flash：速度快（適合摘要）
     * pro：品質高（適合分析）
     */

    /**
     * API Base URL
     */
    'base_url' => 'https://generativelanguage.googleapis.com/v1',
    'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),

    /**
     * Timeout（秒）
     */
    'timeout' => env('GEMINI_TIMEOUT', 60),

    /**
     * 最大回應 token（避免爆量）
     */
    'max_output_tokens' => env('GEMINI_MAX_OUTPUT_TOKENS', 2048),

    /**
     * temperature（創意程度）
     * 0 = 很穩定（推薦摘要）
     * 1 = 比較發散
     */
    'temperature' => env('GEMINI_TEMPERATURE', 0.2),

    /**
     * 預設 system prompt（可選）
     */
    'system_prompt' => env(
        'GEMINI_SYSTEM_PROMPT',
        '你是一個專業的資料分析與摘要助手，請用條列式整理重點。'
    ),

];
