<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentDirectUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SummaryDashboardController;
use App\Http\Controllers\ProjectDashboardController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\UserActivityController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\OnlyOfficeController;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');
Route::get('/summary-dashboard', [SummaryDashboardController::class, 'index'])->middleware(['auth'])->name('summary-dashboard');
Route::get('/summary-dashboard/download', [SummaryDashboardController::class, 'download'])->middleware(['auth'])->name('summary-dashboard.download');
Route::get('/project-dashboard', [ProjectDashboardController::class, 'index'])->middleware(['auth'])->name('project-dashboard');

Route::get('/documents/{id}/office-source', [OnlyOfficeController::class, 'source'])->name('documents.office-source')->where('id', '[0-9]+');
Route::post('/onlyoffice/callback/{id}', [OnlyOfficeController::class, 'callback'])->name('onlyoffice.callback')->where('id', '[0-9]+');

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Project Management (Entity + Project)
    Route::resource('entities', EntityController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('disciplines', DisciplineController::class)->except(['show']);
    Route::get('/user-activities', [UserActivityController::class, 'index'])->name('user-activities.index');

    Route::middleware('role:Admin')->prefix('admin')->group(function () {
        Route::get('/user-access', [UserAccessController::class, 'index'])->name('user-access.index');
        Route::get('/user-access/create', [UserAccessController::class, 'create'])->name('user-access.create');
        Route::post('/user-access', [UserAccessController::class, 'store'])->name('user-access.store');
        Route::get('/user-access/{user}/edit', [UserAccessController::class, 'edit'])->name('user-access.edit');
        Route::put('/user-access/{user}', [UserAccessController::class, 'update'])->name('user-access.update');
    });

    // Document routes:
    // - Keep /documents/... as canonical (named) routes used by Blade links.
    // - Keep legacy /download/pdf/... URLs as fallback for older links/bookmarks.
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download')->where('id', '[0-9]+');
    Route::get('/download/pdf/{id}', [DocumentController::class, 'download'])->where('id', '[0-9]+');
    Route::get('/documents/{id}/view', [DocumentController::class, 'viewPdf'])->name('documents.view')->where('id', '[0-9]+');
    Route::get('/documents/{id}/preview-url', [DocumentController::class, 'previewUrl'])->name('documents.preview-url')->where('id', '[0-9]+');
    Route::get('/download/pdf/{id}/view', [DocumentController::class, 'viewPdf'])->where('id', '[0-9]+');
    Route::get('/documents/{id}/versions', [DocumentController::class, 'versions'])->name('documents.versions')->where('id', '[0-9]+');
    Route::get('/documents/{id}/version-save-status', [DocumentController::class, 'versionSaveStatus'])->name('documents.version-save-status')->where('id', '[0-9]+');
    Route::get('/documents/{id}/edit', [DocumentController::class, 'edit'])->name('documents.edit')->where('id', '[0-9]+');
    Route::post('/documents/{id}/replace', [DocumentController::class, 'replace'])->name('documents.replace')->where('id', '[0-9]+');
    Route::get('/upload', [DocumentController::class, 'create'])->name('documents.upload');
    Route::post('/upload', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('/upload/presign', [DocumentDirectUploadController::class, 'presign'])->name('documents.upload.presign');
    Route::post('/upload/complete', [DocumentDirectUploadController::class, 'complete'])->name('documents.upload.complete');
    Route::post('/upload/chunk-init', [DocumentDirectUploadController::class, 'chunkInit'])->name('documents.upload.chunk-init');
    Route::post('/upload/chunk', [DocumentDirectUploadController::class, 'chunkStore'])->name('documents.upload.chunk');
    Route::post('/upload/chunk-finish', [DocumentDirectUploadController::class, 'chunkFinish'])->name('documents.upload.chunk-finish');
    Route::get('/upload/suggest', [DocumentController::class, 'suggestFromFilename'])->name('documents.suggest');
    Route::get('/search', [DocumentController::class, 'search'])->name('documents.search');
    Route::get('/share/email-suggestions', [DocumentController::class, 'shareEmailSuggestions'])->name('documents.share.email-suggestions');
    Route::post('/documents/{id}/share', [DocumentController::class, 'share'])->name('documents.share')->where('id', '[0-9]+');
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy')->where('id', '[0-9]+');
});

require __DIR__ . '/auth.php';