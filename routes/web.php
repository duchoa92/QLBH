<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryAttributeController;
use App\Http\Controllers\CategoryAttributeValueController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImeiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockImportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trang gốc: điều hướng thẳng, không dùng trang Welcome mặc định của Laravel
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Yêu cầu đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    )->middleware('verified')->name('dashboard');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::patch('/units/{unit}/toggle-status', [UnitController::class, 'toggleStatus'])->name('units.toggleStatus');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Products & Inventory
    |--------------------------------------------------------------------------
    */
    Route::prefix('products')->name('products.')->group(function () {

        /* Static Routes (Đặt lên trước Dynamic Parameters) */
        Route::get('/template', [ProductController::class, 'template'])->name('template');
        Route::post('/import', [ProductController::class, 'import'])->name('import');
        Route::post('/validate', [ProductController::class, 'previewImport'])->name('validate');
        Route::get('/list-import', [ProductController::class, 'listForImport'])->name('listImport');
        Route::get('/search', [ProductController::class, 'search'])->name('search');

        // Export
        Route::post('/export-start', [ProductController::class, 'startExport'])->name('export.start');
        Route::get('/export-check/{id}', [ProductController::class, 'checkExport'])->name('export.check');
        Route::get('/export-download/{id}', [ProductController::class, 'downloadExport'])->name('export.download');
        Route::post('/export-errors', [ProductController::class, 'exportErrors'])->name('export.errors');

        // Trash & Bulk
        Route::get('/trash', [ProductController::class, 'trash'])->middleware('permission:products.view')->name('trash');
        Route::post('/bulk-delete', [ProductController::class, 'bulkDelete'])->name('bulkDelete');
        Route::post('/bulk-restore', [ProductController::class, 'bulkRestore'])->name('bulkRestore');
        Route::post('/bulk-force-delete', [ProductController::class, 'bulkForceDelete'])->name('bulkForceDelete');

        // Print
        Route::post('/print-imei', [ProductController::class, 'printImei'])->name('printImei');
        Route::post('/print-data', [ProductController::class, 'printData'])->name('printData');

        // Preview SKU
        Route::get('/preview-sku', [ProductController::class, 'previewSku'])->middleware('permission:products.create')->name('previewSku');

        // Standard CRUD
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:products.view')->name('index');
        Route::get('/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('create');
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.create')->name('store');

        /* Dynamic Routes (Các route có tham số ID/{product} đặt ở cuối) */
        Route::get('/{product}', [ProductController::class, 'show'])->whereNumber('product')->middleware('permission:products.view')->name('show');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.edit')->name('edit');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('destroy');

        Route::post('/{id}/restore', [ProductController::class, 'restore'])->middleware('permission:products.edit')->name('restore');
        Route::delete('/{id}/force', [ProductController::class, 'forceDelete'])->name('forceDelete');
        Route::patch('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('toggleStatus');
    });

    Route::post('/scan', [ProductController::class, 'scan'])->name('products.scan');

    // Attributes & Variants
    Route::prefix('category-attributes')->group(function () {
        Route::post('/', [CategoryAttributeController::class, 'store'])->name('attributes.store');
        Route::put('/{attribute}', [CategoryAttributeController::class, 'update'])->name('attributes.update');
        Route::delete('/{attribute}', [CategoryAttributeController::class, 'destroy'])->name('attributes.destroy');
    });

    Route::prefix('category-attribute-values')->group(function () {
        Route::get('/{attributeId}', [CategoryAttributeValueController::class, 'index']);
        Route::post('/', [CategoryAttributeValueController::class, 'store']);
        Route::put('/{id}', [CategoryAttributeValueController::class, 'update']);
        Route::delete('/{id}', [CategoryAttributeValueController::class, 'destroy']);
    });

    // Stock Import (Nhập hàng)
    Route::get(
        '/stock-import',
        [StockImportController::class, 'index']
    )->name('stock.index');

    Route::post(
        '/stock-import',
        [StockImportController::class, 'store']
    )->name('stock.import');

    Route::get(
        '/stock-import/{stockImport}',
        [StockImportController::class, 'show']
    )->name('stock.show');

    Route::get('/api/products-with-variants', function () {
        return \App\Models\Product::with([
            'variants:id,product_id,sku,barcode,attributes,cost_price,sell_price,stock',
        ])
        ->select('id', 'name', 'sku', 'cost_price', 'sell_price', 'stock', 'product_type', 'manage_stock_by_serial', 'image')
        ->get();
    });

    /*
    |--------------------------------------------------------------------------
    | Users, Categories & Brands
    |--------------------------------------------------------------------------
    */
    // Users
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

    // Categories
    Route::get('/categories/trash', [CategoryController::class, 'trash'])->middleware('permission:categories.view')->name('categories.trash');
    Route::post('/categories/{id}/restore', [CategoryController::class, 'restore'])->middleware('permission:categories.edit')->name('categories.restore');
    Route::delete('/categories/{id}/force', [CategoryController::class, 'forceDelete'])->name('categories.forceDelete');
    Route::patch('/categories/{id}/toggle-status', [CategoryController::class, 'toggleStatus'])->middleware('permission:categories.edit')->name('categories.toggleStatus');
    Route::resource('categories', CategoryController::class);

    // Brands
    Route::get('/brands/trash', [BrandController::class, 'trash'])->middleware('permission:brands.view')->name('brands.trash');
    Route::post('/brands/{id}/restore', [BrandController::class, 'restore'])->middleware('permission:brands.edit')->name('brands.restore');
    Route::delete('/brands/{id}/force', [BrandController::class, 'forceDelete'])->name('brands.forceDelete');
    Route::patch('/brands/{id}/toggle-status', [BrandController::class, 'toggleStatus'])->middleware('permission:brands.edit')->name('brands.toggleStatus');
    Route::resource('brands', BrandController::class)->middleware([
        'index'   => 'permission:brands.view',
        'create'  => 'permission:brands.create',
        'store'   => 'permission:brands.create',
        'show'    => 'permission:brands.view',
        'edit'    => 'permission:brands.edit',
        'update'  => 'permission:brands.edit',
        'destroy' => 'permission:brands.delete',
    ]);

    /*
    |--------------------------------------------------------------------------
    | POS, Sales & Repairs
    |--------------------------------------------------------------------------
    */
    // POS
    Route::prefix('pos')->name('pos.')->controller(PosController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/scan-imei', 'scanImei')->name('scan-imei');
        Route::post('/checkout', 'checkout')->name('checkout');
        Route::get('/sales/{sale}', 'showSale')->name('sale.show');
        Route::get('/sales/{sale}/receipt', 'receipt')->name('receipt'); // Xử lý hóa đơn trực tiếp tại POS
    });

    // Sales Management
    Route::prefix('sales')->name('sales.')->controller(SaleController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{sale}', 'show')->name('show');
    });
    Route::get('/sales/{sale}/receipt', [SaleReceiptController::class, 'show'])->name('sales.receipt');

    // Repairs (Đã đưa các Route tĩnh lên TRƯỚC Resource)
    Route::get('/repairs/customer-search', [RepairController::class, 'customerSearch'])->name('repairs.customer-search');
    Route::get('/repairs/suggestions', [RepairController::class, 'suggestions'])->name('repairs.suggestions');
    Route::patch('/repairs/{repair}/status', [RepairController::class, 'updateStatus'])->name('repairs.update-status');
    Route::get('/repairs/{repair}/print', [RepairController::class, 'print'])->name('repairs.print');
    Route::resource('repairs', RepairController::class);

    // IMEIs, Customers, Suppliers
    Route::prefix('product-imeis')->name('product-imeis.')->controller(ProductImeiController::class)->group(function () {
        Route::get('/{product}', 'index')->name('index');
        Route::post('/{product}', 'store')->name('store');
    });
    Route::get('/imeis/{imei}', [ProductImeiController::class, 'show'])->name('product-imeis.show');

    Route::resource('customers', CustomerController::class);
    Route::get('/api/suppliers/search', [SupplierController::class, 'search'])->name('api.suppliers.search');
    Route::resource('suppliers', SupplierController::class);

    /*
    |--------------------------------------------------------------------------
    | Reports (Báo cáo & Thống kê)
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/revenue', 'revenue')->name('revenue');
        Route::get('/best-sellers', 'bestSellers')->name('bestSellers');
        Route::get('/inventory', 'inventory')->name('inventory');
        Route::get('/debts', 'debts')->name('debts');
        Route::get('/profit', 'profit')->name('profit');
    });
});

require __DIR__ . '/auth.php';
