<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AnomalyController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SihandalController;
use App\Http\Controllers\SihandalMatchController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::inertia('/login', 'auth/login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Sections gated by role (spec §36, §44): viewers get read-only areas,
    // administration is admin-only.
    Route::middleware('role:admin,verifier')->group(function (): void {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::post('/documents/{document}/process', [DocumentController::class, 'process'])->name('documents.process');
        Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
        Route::post('/documents/{document}/review', [ReviewController::class, 'store'])->name('documents.review');
        Route::get('/validation', [SectionController::class, 'validation'])->name('validation.index');
        Route::get('/anomalies', [AnomalyController::class, 'index'])->name('anomalies.index');

        // Anomaly lifecycle (spec §15, §25): human resolve/dismiss
        // of factual indicators. Requires the review ability.
        Route::post('/anomalies/{anomaly}/resolve', [AnomalyController::class, 'resolve'])->name('anomalies.resolve');
        Route::post('/anomalies/{anomaly}/dismiss', [AnomalyController::class, 'dismiss'])->name('anomalies.dismiss');
        Route::get('/sihandal', [SihandalController::class, 'index'])->name('sihandal.index');

        // SIHANDAL human decisions (spec §17, §48): confirm performs
        // the sync through the API — the only write path to the
        // system of record. Requires the review ability.
        Route::post('/documents/{document}/sihandal/matches/{match}/confirm', [SihandalMatchController::class, 'confirm'])->name('documents.sihandal.match.confirm');
        Route::post('/documents/{document}/sihandal/matches/{match}/reject', [SihandalMatchController::class, 'reject'])->name('documents.sihandal.match.reject');
        Route::post('/documents/{document}/sihandal/syncs/{sync}/retry', [SihandalMatchController::class, 'retry'])->name('documents.sihandal.sync.retry');
    });

    // AI assistant (spec §19, §20, §47): read-only, grounded
    // chat. Tools enforce OPD isolation and view policies.
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai.assistant');
    Route::post('/ai-assistant/chat', [AiAssistantController::class, 'chat'])->name('ai.assistant.chat');

    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/reports', [SectionController::class, 'reports'])->name('reports.index');

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/settings', [SectionController::class, 'settings'])->name('settings.index');
        Route::get('/administration', [SectionController::class, 'administration'])->name('admin.index');
    });
});
