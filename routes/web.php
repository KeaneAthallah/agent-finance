<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AnomalyController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SihandalController;
use App\Http\Controllers\SihandalMatchController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Lightweight reachability probe for the E2E
// webServer check and container health checks —
// no auth, no throttle (spec §35), so it never
// eats the login route's rate-limit budget.
Route::get('/health', fn (): JsonResponse => response()->json(['status' => 'ok']));

// Throttled (spec §35): credential stuffing
// protection on the login form.
Route::middleware(['guest', 'throttle:5,1'])->group(function (): void {
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
    // Throttled (spec §35): every chat turn costs tokens.
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai.assistant');
    Route::post('/ai-assistant/chat', [AiAssistantController::class, 'chat'])->name('ai.assistant.chat')->middleware('throttle:10,1');

    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');

    // Platform surface (spec §44): settings are a read-only
    // view of the effective configuration; administration is
    // the control surface. Page access is admin-only; the
    // mutations inside are super-admin-only (§36).
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/administration', [AdminController::class, 'index'])->name('admin.index');

        Route::post('/administration/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::post('/administration/opds', [AdminController::class, 'storeOpd'])->name('admin.opds.store');
        Route::post('/administration/opds/{opd}', [AdminController::class, 'updateOpd'])->name('admin.opds.update');
        Route::post('/administration/document-types/{documentType}', [AdminController::class, 'updateDocumentType'])->name('admin.document-types.update');
        Route::post('/administration/prompts', [AdminController::class, 'storePrompt'])->name('admin.prompts.store');
        Route::post('/administration/prompts/{prompt}/activate', [AdminController::class, 'activatePrompt'])->name('admin.prompts.activate');
    });
});
