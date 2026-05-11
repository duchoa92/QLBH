<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Welcome
|--------------------------------------------------------------------------
*/

Route::get('/', function () {

    return Inertia::render(
        'Welcome',
        [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]
    );
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get(
    '/dashboard',
    function () {

        return Inertia::render('Dashboard');
    }
)
    ->middleware([
        'auth',
        'verified',
    ])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profile',
        [ProfileController::class, 'edit']
    )->name('profile.edit');

    Route::patch(
        '/profile',
        [ProfileController::class, 'update']
    )->name('profile.update');

    Route::delete(
        '/profile',
        [ProfileController::class, 'destroy']
    )->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'categories',
        CategoryController::class
    )->middleware([
        'index' => 'permission:categories.view',
        'create' => 'permission:categories.create',
        'store' => 'permission:categories.create',
        'edit' => 'permission:categories.edit',
        'update' => 'permission:categories.edit',
        'destroy' => 'permission:categories.delete',
    ]);

    Route::post(
        'categories/{id}/restore',
        [CategoryController::class, 'restore']
    )
        ->middleware('permission:categories.edit')
        ->name('categories.restore');

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
    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'users',
        UserController::class
    )->middleware([
        'index' => 'permission:users.view',
        'create' => 'permission:users.create',
        'store' => 'permission:users.create',
        'edit' => 'permission:users.edit',
        'update' => 'permission:users.edit',
        'destroy' => 'permission:users.delete',
    ]);
});

require __DIR__ . '/auth.php';