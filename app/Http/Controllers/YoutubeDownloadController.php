<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class YoutubeDownloadController extends Controller
{
    public function download(Request $request, string $filename)
    {
        $path = storage_path("app/youtube/video/{$filename}");

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path);
    }
}
