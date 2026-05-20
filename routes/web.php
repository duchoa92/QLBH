<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductImeiController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\RepairController;

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

    Route::get(
        '/categories',
        [CategoryController::class, 'index']
    )
        ->middleware('permission:categories.view')
        ->name('categories.index');

    Route::get(
        '/categories/create',
        [CategoryController::class, 'create']
    )
        ->middleware('permission:categories.create')
        ->name('categories.create');

    Route::post(
        '/categories',
        [CategoryController::class, 'store']
    )
        ->middleware('permission:categories.create')
        ->name('categories.store');

    Route::get(
        '/categories/{category}/edit',
        [CategoryController::class, 'edit']
    )
        ->middleware('permission:categories.edit')
        ->name('categories.edit');

    Route::put(
        '/categories/{category}',
        [CategoryController::class, 'update']
    )
        ->middleware('permission:categories.edit')
        ->name('categories.update');

    Route::delete(
        '/categories/{category}',
        [CategoryController::class, 'destroy']
    )
        ->middleware('permission:categories.delete')
        ->name('categories.destroy');

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
    // Cập nhật sản phẩm
    Route::put(
        '/products/{product}',
        [ProductController::class, 'update']
    )
        ->middleware('permission:products.edit')
        ->name('products.update');
    // Xóa sản phẩm
    Route::delete(
        '/products/{product}',
        [ProductController::class, 'destroy']
    )
        ->middleware('permission:products.delete')
        ->name('products.destroy');
    // Hiển thị sản phẩm đã xóa
    Route::get(
        '/products-trash',
        [ProductController::class, 'trash']
    )
        ->middleware('permission:products.view')
        ->name('products.trash');
    // Khôi phục sản phẩm
    Route::post(
        '/products/{id}/restore',
        [ProductController::class, 'restore']
    )
        ->middleware('permission:products.edit')
        ->name('products.restore');
    // Hiển thị chi tiết sản phẩm
    Route::get(
        '/products/{product}',
        [ProductController::class, 'show']
    )
        ->middleware('permission:products.view')
        ->name('products.show');

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/users',
        [UserController::class, 'index']
    )
        ->middleware('permission:users.view')
        ->name('users.index');

    Route::get(
        '/users/create',
        [UserController::class, 'create']
    )
        ->middleware('permission:users.create')
        ->name('users.create');

    Route::post(
        '/users',
        [UserController::class, 'store']
    )
        ->middleware('permission:users.create')
        ->name('users.store');

    Route::get(
        '/users/{user}/edit',
        [UserController::class, 'edit']
    )
        ->middleware('permission:users.edit')
        ->name('users.edit');

    Route::put(
        '/users/{user}',
        [UserController::class, 'update']
    )
        ->middleware('permission:users.edit')
        ->name('users.update');

    Route::delete(
        '/users/{user}',
        [UserController::class, 'destroy']
    )
        ->middleware('permission:users.delete')
        ->name('users.destroy');


    /*|--------------------------------------------------------------------------
    | Brands
    |--------------------------------------------------------------------------
    */
    Route::resource(
        'brands',
        BrandController::class
    );

    // Hiển thị thương hiệu đã xóa
    Route::get(
        '/brands-trash',
        [BrandController::class, 'trash']
    )->name('brands.trash');

    // Khôi phục thương hiệu
    Route::post(
        '/brands/{id}/restore',
        [BrandController::class, 'restore']
    )->name('brands.restore');


    // hiển thị POS 
    Route::prefix('pos')

        ->name('pos.')

        ->controller(PosController::class)

        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name('index');

            Route::post(
                '/scan-imei',
                'scanImei'
            )->name('scan-imei');

            Route::post(
                '/checkout',
                'checkout'
            )->name('checkout');

            Route::get(
                '/sales/{sale}',
                'showSale'
            )->name('sale.show');

        });

    // Sản phẩm IMEI Routes
    Route::prefix('product-imeis')

        ->name('product-imeis.')

        ->controller(ProductImeiController::class)

        ->group(function () {

            Route::get(
                '/{product}',
                'index'
            )->name('index');

            Route::post(
                '/{product}',
                'store'
            )->name('store');
        });

    // Sales Routes
    Route::prefix('sales')

        ->name('sales.')

        ->controller(SaleController::class)

        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name('index');

            Route::get(
                '/{sale}',
                'show'
            )->name('show');
        });
    

    // Tìm kiếm khách hàng sửa chữa
    Route::get(
        '/repairs/customer-search',
        [RepairController::class, 'customerSearch']
    )->name('repairs.customer-search');

    // Gợi ý thông tin sửa chữa
    Route::get(
        '/repairs/suggestions',
        [RepairController::class, 'suggestions']
    )->name('repairs.suggestions');

   // sửa chữa Routes
    Route::resource(
        'repairs',
        RepairController::class
    )->except([
        'show',
    ]);



});

require __DIR__ . '/auth.php';