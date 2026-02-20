<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\ProjectController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Project Management (Entity + Project)
    Route::resource('entities', EntityController::class);
    Route::resource('projects', ProjectController::class);

    // Document Routes – download (two URLs so one may work on your server)
    Route::get('/download/pdf/{id}', [DocumentController::class, 'download'])->name('documents.download')->where('id', '[0-9]+');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->where('id', '[0-9]+');
    Route::get('/upload', [DocumentController::class, 'create'])->name('documents.upload');
    Route::post('/upload', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/search', [DocumentController::class, 'search'])->name('documents.search');
});

require __DIR__ . '/auth.php';