<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;

// Product routes
Route::middleware(['auth',])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/products',
        [ProductController::class, 'index']
    )
        ->middleware('permission:products.view')
        ->name('products.index');

    Route::get(
        '/products/create',
        [ProductController::class, 'create']
    )
        ->middleware('permission:products.create')
        ->name('products.create');

    Route::post(
        '/products',
        [ProductController::class, 'store']
    )
        ->middleware('permission:products.create')
        ->name('products.store');

    Route::get(
        '/products/{product}/edit',
        [ProductController::class, 'edit']
    )
        ->middleware('permission:products.edit')
        ->name('products.edit');

    Route::put(
        '/products/{product}',
        [ProductController::class, 'update']
    )
        ->middleware('permission:products.edit')
        ->name('products.update');

    Route::delete(
        '/products/{product}',
        [ProductController::class, 'destroy']
    )
        ->middleware('permission:products.delete')
        ->name('products.destroy');

    Route::get(
        '/products-trash',
        [ProductController::class, 'trash']
    )
        ->middleware('permission:products.view')
        ->name('products.trash');

    Route::post(
        '/products/{id}/restore',
        [ProductController::class, 'restore']
    )
        ->middleware('permission:products.edit')
        ->name('products.restore');


        // User routes
    Route::resource(
       'users', UserController::class
    )
        ->middleware([
        'index' => 'permission:users.view',
        'create' => 'permission:users.create',
        'store' => 'permission:users.create',
        ]);
});

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('categories', CategoryController::class);
});

Route::post(
    'categories/{id}/restore',
    [CategoryController::class, 'restore']
)->name('categories.restore');

require __DIR__.'/auth.php';
