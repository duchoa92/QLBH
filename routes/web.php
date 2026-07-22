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
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Controllers\SupplierController;


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
| Products
|--------------------------------------------------------------------------
*/

Route::prefix('products')
    ->name('products.')
    ->middleware('auth')
    ->group(function () {

        /* ===== STATIC ROUTES (PHẢI ĐẶT TRÊN) ===== */

        // import export
        Route::get('/template', [ProductController::class, 'template'])->name('template');
        Route::post('/import', [ProductController::class, 'import'])->name('import');
        Route::post('/validate', [ProductController::class, 'previewImport'])->name('validate');
        Route::get('/export-data', [ProductController::class, 'exportData'])->name('exportData');
        
        // trash
        Route::get('/trash', [ProductController::class, 'trash'])
            ->middleware('permission:products.view')
            ->name('trash');

        // bulk
        Route::post('/bulk-delete', [ProductController::class, 'bulkDelete'])->name('bulkDelete');
        Route::post('/bulk-restore', [ProductController::class, 'bulkRestore'])->name('bulkRestore');
        Route::post('/bulk-force-delete', [ProductController::class, 'bulkForceDelete'])->name('bulkForceDelete');

        // print
        Route::post('/print-imei', [ProductController::class, 'printImei'])->name('printImei');
        Route::post('/print-data', [ProductController::class, 'printData'])->name('printData');

        /* ===== CRUD ===== */

        Route::get('/', [ProductController::class, 'index'])
            ->middleware('permission:products.view')
            ->name('index');

        Route::get('/create', [ProductController::class, 'create'])
            ->middleware('permission:products.create')
            ->name('create');

        Route::post('/', [ProductController::class, 'store'])
            ->middleware('permission:products.create')
            ->name('store');

        /* ===== DYNAMIC ROUTES (ĐẶT CUỐI) ===== */

        Route::get('/{product}', [ProductController::class, 'show'])
            ->whereNumber('product')
            ->middleware('permission:products.view')
            ->name('show');

        Route::get('/{product}/edit', [ProductController::class, 'edit'])
            ->middleware('permission:products.edit')
            ->name('edit');

        Route::put('/{product}', [ProductController::class, 'update'])
            ->middleware('permission:products.edit')
            ->name('update');

        Route::delete('/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:products.delete')
            ->name('destroy');

        // restore / force
        Route::post('/{id}/restore', [ProductController::class, 'restore'])
            ->middleware('permission:products.edit')
            ->name('restore');

        Route::delete('/{id}/force', [ProductController::class, 'forceDelete'])
            ->name('forceDelete');

        // toggle
        Route::patch('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])
            ->name('toggleStatus');

        

    });



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

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */


    // Khôi phục danh mục
    Route::post('/categories/{id}/restore', [CategoryController::class, 'restore'])
        ->middleware('permission:categories.edit')    
        ->name('categories.restore');
    // chuyển vào thung rác
    Route::get('/categories/trash', [CategoryController::class, 'trash'])
        ->middleware('permission:categories.view')
        ->name('categories.trash');
    // xóa vĩnh viễn 
    Route::delete('/categories/{id}/force', [CategoryController::class, 'forceDelete'])
        ->name('categories.forceDelete');
    // Toggle trạng thái danh mục
    Route::patch('/categories/{id}/toggle-status', [CategoryController::class, 'toggleStatus'])
        ->middleware('permission:categories.edit')
        ->name('categories.toggleStatus');

    Route::resource('categories', CategoryController::class);


        
    /*|--------------------------------------------------------------------------
    | Brands
    |--------------------------------------------------------------------------
    */


    // Thùng rác & restore (Brand)
    Route::get('/brands/trash', [BrandController::class, 'trash'])
        ->middleware('permission:brands.view')
        ->name('brands.trash');

    Route::post('/brands/{id}/restore', [BrandController::class, 'restore'])
        ->middleware('permission:brands.edit')
        ->name('brands.restore');

    Route::delete('/brands/{id}/force', [BrandController::class, 'forceDelete'])
        ->name('brands.forceDelete');

    Route::patch('/brands/{id}/toggle-status', [BrandController::class, 'toggleStatus'])
        ->middleware('permission:brands.edit')
        ->name('brands.toggleStatus');

    // CRUD chính - có permission
    Route::resource('brands', BrandController::class)
        ->middleware([
            'index'   => 'permission:brands.view',
            'create'  => 'permission:brands.create',
            'store'   => 'permission:brands.create',
            'show'    => 'permission:brands.view',
            'edit'    => 'permission:brands.edit',
            'update'  => 'permission:brands.edit',
            'destroy' => 'permission:brands.delete',
        ]);



        
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

            Route::get(
                '/sales/{sale}/receipt',
                'receipt'
            )->name('receipt');

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
    );

        // Hiển thị chi tiết sửa chữa
    Route::patch(

        'repairs/{repair}/status',

        [RepairController::class, 'updateStatus']

    )->name('repairs.update-status');


    // Mẫu in hóa đơn sửa chữa
    Route::get(

        'repairs/{repair}/print',

        [RepairController::class, 'print']

    )->name('repairs.print');

    // Khách hàng Routes
    Route::resource('customers', CustomerController::class);

    // In HĐ
    Route::get(
        '/sales/{sale}/receipt',
        [SaleReceiptController::class, 'show']
    )->name('sales.receipt');


    // Hiển thị chi tiết sản phẩm IMEI
    Route::get(
        '/imeis/{imei}',
        [ProductImeiController::class, 'show']
    )->name('product-imeis.show');


    // Nhà cung cấp
    Route::resource(
        'suppliers',
        SupplierController::class
    );


    Route::post('/scan', [ProductController::class, 'scan']);

});






require __DIR__ . '/auth.php';