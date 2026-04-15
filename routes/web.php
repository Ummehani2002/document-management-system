<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDashboardController;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');
Route::get('/project-dashboard', [ProjectDashboardController::class, 'index'])->middleware(['auth'])->name('project-dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Project Management (Entity + Project)
    Route::resource('entities', EntityController::class);
    Route::resource('projects', ProjectController::class);

    // Document routes:
    // - Keep /documents/... as canonical (named) routes used by Blade links.
    // - Keep legacy /download/pdf/... URLs as fallback for older links/bookmarks.
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download')->where('id', '[0-9]+');
    Route::get('/download/pdf/{id}', [DocumentController::class, 'download'])->where('id', '[0-9]+');
    Route::get('/documents/{id}/view', [DocumentController::class, 'viewPdf'])->name('documents.view')->where('id', '[0-9]+');
    Route::get('/download/pdf/{id}/view', [DocumentController::class, 'viewPdf'])->where('id', '[0-9]+');
    Route::get('/upload', [DocumentController::class, 'create'])->name('documents.upload');
    Route::post('/upload', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/upload/suggest', [DocumentController::class, 'suggestFromFilename'])->name('documents.suggest');
    Route::get('/search', [DocumentController::class, 'search'])->name('documents.search');
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy')->where('id', '[0-9]+');
});

require __DIR__ . '/auth.php';