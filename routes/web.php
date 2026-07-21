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
        
    // Chuyển vào thùng rác
    Route::delete('/products/{product}',[ProductController::class, 'destroy'])
        ->middleware('permission:products.delete')
        ->name('products.destroy');

    // Hiển thị sản phẩm trong thùng rác
    Route::get('/products/trash', [ProductController::class, 'trash'])
        ->middleware('permission:products.view')
        ->name('products.trash');

    // Chuyển nhiều sản phẩm vào thùng rác
    Route::post('/products/bulk-delete', [ProductController::class, 'bulkDelete'])
    ->name('products.bulkDelete');
    // Khôi phục sản phẩm
    Route::post('/products/{id}/restore', [ProductController::class, 'restore'])
        ->middleware('permission:products.edit')
        ->name('products.restore');
    // Xóa hẳn sản phẩm
    Route::delete('/products/{id}/force', [ProductController::class, 'forceDelete'])
        ->name('products.forceDelete');
    // Khôi phục nhiều sản phẩm
    Route::post('/products/bulk-restore', [ProductController::class, 'bulkRestore'])
        ->name('products.bulkRestore');
    // Xóa Nhiều
    Route::post('/products/bulk-force-delete', [ProductController::class, 'bulkForceDelete'])
        ->name('products.bulkForceDelete');
    
    // Hiển thị chi tiết sản phẩm
    Route::get(
        '/products/{product}',
        [ProductController::class, 'show']
    )
        ->middleware('permission:products.view')
        ->name('products.show');

    // In tem sp
    Route::post('/products/print-imei', [ProductController::class, 'printImei'])
    ->name('products.printImei');

    Route::post('/products/print-data', [ProductController::class, 'printData']);

    // Trạng thái SP
    Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus']);

    // nhập xuất Sản Phẩm
    Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');


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