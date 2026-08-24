<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Log::info('Location API received', [
            'request' => $request->all(),
        ]);

        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'device_name' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        // 暫時先確認 API 有正常收到資料
        return response()->json([
            'success' => true,
            'message' => 'Location received successfully.',
            'data' => $validated,
        ]);
    }
}
