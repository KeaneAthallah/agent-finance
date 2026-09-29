<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SectionController;
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
        Route::get('/review', [SectionController::class, 'review'])->name('review.index');
        Route::get('/validation', [SectionController::class, 'validation'])->name('validation.index');
        Route::get('/anomalies', [SectionController::class, 'anomalies'])->name('anomalies.index');
        Route::get('/sihandal', [SectionController::class, 'sihandal'])->name('sihandal.index');
    });

    Route::get('/ai-assistant', [SectionController::class, 'aiAssistant'])->name('ai.assistant');
    Route::get('/activity', [SectionController::class, 'activity'])->name('activity.index');
    Route::get('/reports', [SectionController::class, 'reports'])->name('reports.index');

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/settings', [SectionController::class, 'settings'])->name('settings.index');
        Route::get('/administration', [SectionController::class, 'administration'])->name('admin.index');
    });
});
