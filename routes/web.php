<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetupAdminController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| PUBLIC
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

/*
|--------------------------------------------------------------------------
| SETUP ADMIN (CHỈ KHI CHƯA CÓ USER)
|--------------------------------------------------------------------------
*/
Route::get('/setup-admin', [SetupAdminController::class, 'index'])
    ->middleware('guest')
    ->name('setup.admin');

Route::post('/setup-admin', [SetupAdminController::class, 'store'])
    ->middleware('guest')
    ->name('setup.admin.store');

/*
|--------------------------------------------------------------------------
| AUTHENTICATED
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
