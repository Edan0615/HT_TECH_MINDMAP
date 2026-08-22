<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MindmapController;
use App\Http\Controllers\ProjectReaderController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [MindmapController::class, 'index'])->name('dashboard');
    Route::get('/mindmaps/{id}', [MindmapController::class, 'show'])->name('mindmaps.show');
    Route::post('/mindmaps', [MindmapController::class, 'store'])->name('mindmaps.store');
    Route::delete('/mindmaps/{id}', [MindmapController::class, 'destroy'])->name('mindmaps.destroy');
    
    // Project Reader Routes
    Route::get('/api/projects/users', [ProjectReaderController::class, 'listUsers']);
    Route::get('/api/projects', [ProjectReaderController::class, 'listProjects']);
    Route::post('/api/projects/tree', [ProjectReaderController::class, 'getProjectTree']);
    Route::post('/api/projects/read', [ProjectReaderController::class, 'readFile']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
