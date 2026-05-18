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


    /*|--------------------------------------------------------------------------
    | Brands
    |--------------------------------------------------------------------------
    */
    Route::resource(
        'brands',
        BrandController::class
    );

    // Hiển thị thương hiệu đã xóa
    Route::post(
        'brands/{id}/restore',
        [BrandController::class, 'restore']
    )->name('brands.restore');


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


    // Hiển thịPOS
    Route::get(
        '/pos',
        [PosController::class, 'index']
    )
        ->middleware('permission:pos.access')
        ->name('pos.index');

    // Quét IMEI
    Route::post(
        '/pos/scan-imei',
        [PosController::class, 'scanImei']
    )
        ->middleware('permission:pos.access')
        ->name('pos.scan-imei');

    // Thanh toán
    Route::post(
        '/pos/checkout',
        [PosController::class, 'checkout']
    )
        ->middleware('permission:sales.create')
        ->name('pos.checkout');

    // POS Routes
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

    // sửa chữa Routes
    Route::resource(
        'repairs',
        RepairController::class
    );


});

require __DIR__ . '/auth.php';