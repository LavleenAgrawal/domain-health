<?php

use App\Http\Controllers\DomainCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'throttle:domain-health'])->group(function (): void {
    Route::post('/checks', [DomainCheckController::class, 'store']);
    Route::post('/checks/upload-preview', [DomainCheckController::class, 'previewUpload']);
    Route::middleware('job.owner')->group(function (): void {
        Route::get('/checks/{checkJob}', [DomainCheckController::class, 'show']);
        Route::get('/checks/{checkJob}/results', [DomainCheckController::class, 'results']);
        Route::get('/checks/{checkJob}/export', [DomainCheckController::class, 'export']);
        Route::post('/checks/{checkJob}/retry', [DomainCheckController::class, 'retry']);
        // The browser polls the lightweight job/results endpoints. Retire the old
        // long-lived stream so stale tabs cannot monopolize PHP's local server.
        Route::get('/checks/{checkJob}/events', fn () => response()->json(['message' => 'Live streaming has been replaced by polling.'], 410));
    });
});
