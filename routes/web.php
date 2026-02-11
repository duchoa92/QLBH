<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetupAdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
        */
        Route::get('/dashboard', function () {
            return Inertia::render('Dashboard');
        })->name('dashboard');

        /*
        | PROFILE
        */
        Route::get('/profile', [ProfileController::class, 'edit'])
            ->name('profile.edit');

        Route::patch('/profile', [ProfileController::class, 'update'])
            ->name('profile.update');

        Route::delete('/profile', [ProfileController::class, 'destroy'])
            ->name('profile.destroy');

        /*
        |--------------------------------------------------------------------------
        | USERS MODULE
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:users.view')->group(function () {

            Route::get('/users', [UserController::class, 'index'])
                ->name('users.index');

            Route::get('/users/create', [UserController::class, 'create'])
                ->middleware('permission:users.create')
                ->name('users.create');

            Route::post('/users', [UserController::class, 'store'])
                ->middleware('permission:users.create')
                ->name('users.store');

            Route::get('/users/{user}/edit', [UserController::class, 'edit'])
                ->middleware('permission:users.update')
                ->name('users.edit');

            Route::put('/users/{user}', [UserController::class, 'update'])
                ->middleware('permission:users.update')
                ->name('users.update');

            Route::delete('/users/{user}', [UserController::class, 'destroy'])
                ->middleware('permission:users.delete')
                ->name('users.destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | REGISTER (CHỈ KHI CHƯA CÓ USER)
    |--------------------------------------------------------------------------
    */
    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->middleware('guest')
        ->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('guest');

    require __DIR__ . '/auth.php';
});
