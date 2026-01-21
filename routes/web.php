<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetupAdminController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\RegisteredUserController;

Route::middleware('only_first_user')->group(function () {

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
        ->name('setup.admin');

    Route::post('/setup-admin', [SetupAdminController::class, 'store'])
        ->name('setup.admin.store');

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth')->group(function () {

        /*
        | DASHBOARD
        | Không cần permission (ai đăng nhập cũng vào được)
        */
        Route::get('/dashboard', function () {
            return Inertia::render('Dashboard');
        })->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

     /*
        |--------------------------------------------------------------------------
        | USERS MODULE (VÍ DỤ ÁP PERMISSION)
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:users.view')->group(function () {
            Route::get('/users', function () {
                return Inertia::render('Users/Index');
            })->name('users.index');
        });
    });


    // Tạo Role Register

    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->middleware('guest')
        ->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('guest');


    require __DIR__.'/auth.php';
});
