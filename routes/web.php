<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DonationController;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Response;

Route::get('/donation', [DonationController::class, 'index'])->name('donate');

Route::get('/youtube/download/{filename}', function ($filename) {
    $path = storage_path('app/youtube/' . $filename);

    abort_unless(file_exists($path), 404);

    return response()->download($path);
})->name('youtube.download')->middleware('signed');
