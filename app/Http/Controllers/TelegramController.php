<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\TelegramService;

class TelegramController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function handleWebhook(Request $request)
    {
        Log::info('Received update:', $request->all());  // 記錄接收到的更新

        $update = $request->all();
        $this->telegramService->handleUpdate($update);  // 處理更新

        return response()->json(['status' => 'success']);
    }
}
