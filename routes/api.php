<?php

use App\Http\Controllers\Api\V1\DocumentController;
use Illuminate\Support\Facades\Route;

/*
 * Machine-client API (spec §43): Sanctum personal access
 * tokens only. The same OPD isolation and policy checks as
 * the UI apply — the API is a second client, not a bypass.
 */
Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::post('/documents/{document}/review', [DocumentController::class, 'review']);

    Route::get('/documents/{document}/sihandal/matches', [DocumentController::class, 'matches']);
    Route::post('/documents/{document}/sihandal/matches/{match}/confirm', [DocumentController::class, 'confirmMatch']);
});
