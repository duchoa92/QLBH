<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BackupCloudOAuthController;
use App\Http\Controllers\CategoryAttributeController;
use App\Http\Controllers\CategoryAttributeValueController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DebtPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImeiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockImportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trang gốc: điều hướng thẳng, không dùng trang Welcome mặc định của Laravel
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect(auth()->user()->landingPath())
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Yêu cầu đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Dashboard
    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    )->middleware(['verified', 'permission:dashboard.view'])->name('dashboard');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->middleware('permission:settings.edit|payment_accounts.manage')->name('settings.update');
    Route::get('/backups', [BackupController::class, 'index'])->middleware('permission:backups.manage')->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->middleware('permission:backups.manage')->name('backups.store');
    Route::post('/backups/cloud-connections', [BackupController::class, 'storeCloudConnection'])->middleware('permission:backups.manage')->name('backups.cloud-connections.store');
    Route::get('/backups/cloud/oauth/{provider}/start', [BackupCloudOAuthController::class, 'start'])->middleware('permission:backups.manage')->name('backups.cloud.oauth.start');
    Route::get('/backups/cloud/oauth/{provider}/callback', [BackupCloudOAuthController::class, 'callback'])->middleware('permission:backups.manage')->name('backups.cloud.oauth.callback');
    Route::delete('/backups/cloud-connections/{connection}', [BackupController::class, 'destroyCloudConnection'])->middleware('permission:backups.manage')->name('backups.cloud-connections.destroy');
    Route::patch('/backups/cloud-connections/{connection}/toggle', [BackupController::class, 'toggleCloudConnection'])->middleware('permission:backups.manage')->name('backups.cloud-connections.toggle');
    Route::post('/backups/cloud/{connection}/inspect', [BackupController::class, 'inspectCloudBackup'])->middleware('permission:backups.manage')->name('backups.cloud.inspect');
    Route::delete('/backups/cloud/{connection}', [BackupController::class, 'destroyCloudBackup'])->middleware('permission:backups.manage')->name('backups.cloud.destroy');
    Route::post('/backups/import', [BackupController::class, 'import'])->middleware('permission:backups.manage')->name('backups.import');
    Route::get('/backups/progress/{operation}', [BackupController::class, 'progress'])->middleware('permission:backups.manage')->name('backups.progress');
    Route::post('/backups/{backup}/inspect', [BackupController::class, 'inspect'])->middleware('permission:backups.manage')->name('backups.inspect');
    Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->middleware('permission:backups.manage')->name('backups.restore');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->middleware('permission:backups.manage')->name('backups.download');
    Route::delete('/backups/bulk', [BackupController::class, 'destroyMany'])->middleware('permission:backups.manage')->name('backups.bulk-destroy');
    Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->middleware('permission:backups.manage')->name('backups.destroy');
    Route::put('/backups/schedule', [BackupController::class, 'updateSchedule'])->middleware('permission:backups.manage')->name('backups.schedule');
    Route::post('/units', [UnitController::class, 'store'])->middleware('permission:units.manage')->name('units.store');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.manage')->name('units.update');
    Route::patch('/units/{unit}/toggle-status', [UnitController::class, 'toggleStatus'])->middleware('permission:units.manage')->name('units.toggleStatus');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.manage')->name('units.destroy');

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
        Route::get('/template', [ProductController::class, 'template'])->middleware('permission:products.import')->name('template');
        Route::post('/import', [ProductController::class, 'import'])->middleware('permission:products.import')->name('import');
        Route::post('/validate', [ProductController::class, 'previewImport'])->middleware('permission:products.import')->name('validate');
        Route::get('/list-import', [ProductController::class, 'listForImport'])->middleware('permission:products.view')->name('listImport');
        Route::get('/search', [ProductController::class, 'search'])->middleware('permission:products.view')->name('search');

        // Export
        Route::post('/export-start', [ProductController::class, 'startExport'])->middleware('permission:products.export')->name('export.start');
        Route::get('/export-check/{id}', [ProductController::class, 'checkExport'])->middleware('permission:products.export')->name('export.check');
        Route::get('/export-download/{id}', [ProductController::class, 'downloadExport'])->middleware('permission:products.export')->name('export.download');
        Route::post('/export-errors', [ProductController::class, 'exportErrors'])->middleware('permission:products.export')->name('export.errors');

        // Trash & Bulk
        Route::get('/trash', [ProductController::class, 'trash'])->middleware('permission:products.view')->name('trash');
        Route::post('/bulk-delete', [ProductController::class, 'bulkDelete'])->middleware('permission:products.delete')->name('bulkDelete');
        Route::post('/bulk-restore', [ProductController::class, 'bulkRestore'])->middleware('permission:products.edit')->name('bulkRestore');
        Route::post('/bulk-force-delete', [ProductController::class, 'bulkForceDelete'])->middleware('permission:products.delete')->name('bulkForceDelete');

        // Print
        Route::post('/print-imei', [ProductController::class, 'printImei'])->middleware('permission:products.view')->name('printImei');
        Route::post('/print-data', [ProductController::class, 'printData'])->middleware('permission:products.view')->name('printData');

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
        Route::delete('/{id}/force', [ProductController::class, 'forceDelete'])->middleware('permission:products.delete')->name('forceDelete');
        Route::patch('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->middleware('permission:products.edit')->name('toggleStatus');
    });

    Route::post('/scan', [ProductController::class, 'scan'])->middleware('permission:products.view|pos.access')->name('products.scan');

    // Attributes & Variants
    Route::prefix('category-attributes')->group(function () {
        Route::post('/', [CategoryAttributeController::class, 'store'])->middleware('permission:categories.edit')->name('attributes.store');
        Route::put('/{attribute}', [CategoryAttributeController::class, 'update'])->middleware('permission:categories.edit')->name('attributes.update');
        Route::delete('/{attribute}', [CategoryAttributeController::class, 'destroy'])->middleware('permission:categories.edit')->name('attributes.destroy');
    });

    Route::prefix('category-attribute-values')->group(function () {
        Route::get('/{attributeId}', [CategoryAttributeValueController::class, 'index'])->middleware('permission:categories.view');
        Route::post('/', [CategoryAttributeValueController::class, 'store'])->middleware('permission:categories.edit');
        Route::put('/{id}', [CategoryAttributeValueController::class, 'update'])->middleware('permission:categories.edit');
        Route::delete('/{id}', [CategoryAttributeValueController::class, 'destroy'])->middleware('permission:categories.edit');
    });

    // Stock Import (Nhập hàng)
    Route::get(
        '/stock-import',
        [StockImportController::class, 'index']
    )->middleware('permission:stock_imports.view')->name('stock.index');

    Route::post(
        '/stock-import',
        [StockImportController::class, 'store']
    )->middleware('permission:stock_imports.create')->name('stock.import');

    Route::get(
        '/stock-import/{stockImport}',
        [StockImportController::class, 'show']
    )->middleware('permission:stock_imports.view')->name('stock.show');

    Route::get('/api/products-with-variants', function () {
        return \App\Models\Product::with([
            'variants:id,product_id,sku,barcode,attributes,cost_price,sell_price,stock',
        ])
        ->select('id', 'name', 'sku', 'cost_price', 'sell_price', 'stock', 'product_type', 'manage_stock_by_serial', 'image')
        ->get();
    })->middleware('permission:products.view|pos.access');

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
    Route::get('/roles/options', [RoleManagementController::class, 'options'])->middleware('permission:roles.manage')->name('roles.options');
    Route::post('/roles', [RoleManagementController::class, 'store'])->middleware('permission:roles.manage')->name('roles.store');
    Route::put('/roles/{role}', [RoleManagementController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');
    Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy'])->middleware('permission:roles.manage')->name('roles.destroy');

    // Categories
    Route::get('/categories/trash', [CategoryController::class, 'trash'])->middleware('permission:categories.view')->name('categories.trash');
    Route::post('/categories/{id}/restore', [CategoryController::class, 'restore'])->middleware('permission:categories.edit')->name('categories.restore');
    Route::delete('/categories/{id}/force', [CategoryController::class, 'forceDelete'])->middleware('permission:categories.delete')->name('categories.forceDelete');
    Route::patch('/categories/{id}/toggle-status', [CategoryController::class, 'toggleStatus'])->middleware('permission:categories.edit')->name('categories.toggleStatus');
    Route::resource('categories', CategoryController::class)
        ->middlewareFor(['index', 'show'], 'permission:categories.view')
        ->middlewareFor(['create', 'store'], 'permission:categories.create')
        ->middlewareFor(['edit', 'update'], 'permission:categories.edit')
        ->middlewareFor('destroy', 'permission:categories.delete');

    // Brands
    Route::get('/brands/trash', [BrandController::class, 'trash'])->middleware('permission:brands.view')->name('brands.trash');
    Route::post('/brands/{id}/restore', [BrandController::class, 'restore'])->middleware('permission:brands.edit')->name('brands.restore');
    Route::delete('/brands/{id}/force', [BrandController::class, 'forceDelete'])->middleware('permission:brands.delete')->name('brands.forceDelete');
    Route::patch('/brands/{id}/toggle-status', [BrandController::class, 'toggleStatus'])->middleware('permission:brands.edit')->name('brands.toggleStatus');
    Route::resource('brands', BrandController::class)
        ->middlewareFor(['index', 'show'], 'permission:brands.view')
        ->middlewareFor(['create', 'store'], 'permission:brands.create')
        ->middlewareFor(['edit', 'update'], 'permission:brands.edit')
        ->middlewareFor('destroy', 'permission:brands.delete');

    /*
    |--------------------------------------------------------------------------
    | POS, Sales & Repairs
    |--------------------------------------------------------------------------
    */
    // POS
    Route::prefix('pos')->name('pos.')->controller(PosController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:pos.access')->name('index');
        Route::post('/scan-imei', 'scanImei')->middleware('permission:pos.access')->name('scan-imei');
        Route::post('/checkout', 'checkout')->middleware('permission:pos.access')->name('checkout');
        Route::get('/sales/{sale}', 'showSale')->middleware('permission:sales.view')->name('sale.show');
        Route::get('/sales/{sale}/receipt', 'receipt')->middleware('permission:sales.view')->name('receipt'); // Xử lý hóa đơn trực tiếp tại POS
    });

    // Sales Management
    Route::prefix('sales')->name('sales.')->controller(SaleController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:sales.view')->name('index');
        Route::get('/{sale}', 'show')->middleware('permission:sales.view')->name('show');
        // Hủy hóa đơn
        Route::post('/{sale}/cancel', 'cancel')->middleware('permission:sales.cancel')->name('cancel');
    });
    Route::get('/sales/{sale}/receipt', [SaleReceiptController::class, 'show'])->middleware('permission:sales.view')->name('sales.receipt');

    // Repairs (Đã đưa các Route tĩnh lên TRƯỚC Resource)
    Route::get('/repairs/suggestions', [RepairController::class, 'suggestions'])->middleware('permission:repairs.view')->name('repairs.suggestions');
    Route::get('/repairs/customers/{customer}/devices', [RepairController::class, 'customerDevices'])->middleware('permission:repairs.view')->name('repairs.customer-devices');
    Route::patch('/repairs/{repair}/status', [RepairController::class, 'updateStatus'])->middleware('permission:repairs.edit')->name('repairs.update-status');
    Route::patch('/repairs/{repair}/warranty/decline', [RepairController::class, 'declineWarranty'])->middleware('permission:repairs.edit')->name('repairs.warranty-decline');
    Route::post('/repairs/{repair}/complete', [RepairController::class, 'complete'])->middleware('permission:repairs.complete')->name('repairs.complete');
    Route::post('/repairs/{repair}/return', [RepairController::class, 'returnToCustomer'])->middleware('permission:repairs.return')->name('repairs.return');
    Route::get('/repairs/{repair}/print', [RepairController::class, 'print'])->middleware('permission:repairs.view')->name('repairs.print');
    Route::get('/repairs', [RepairController::class, 'index'])->middleware('permission:repairs.view')->name('repairs.index');
    Route::post('/repairs', [RepairController::class, 'store'])->middleware('permission:repairs.create')->name('repairs.store');
    Route::match(['put', 'patch'], '/repairs/{repair}', [RepairController::class, 'update'])->middleware('permission:repairs.edit')->name('repairs.update');

    // IMEIs, Customers, Suppliers
    Route::get('/api/imeis/lookup', [ProductImeiController::class, 'lookup'])->middleware('permission:products.view')->name('api.imeis.lookup');
    Route::patch('/product-imeis/imei/{imei}/price', [ProductImeiController::class, 'updatePrice'])->middleware('permission:products.edit')->name('product-imeis.update-price');

    Route::prefix('product-imeis')->name('product-imeis.')->controller(ProductImeiController::class)->group(function () {
        Route::get('/{product}', 'index')->middleware('permission:products.view')->name('index');
        Route::post('/{product}', 'store')->middleware('permission:products.edit')->name('store');
    });
    Route::get('/imeis/lookup', [ProductImeiController::class, 'lookupPage'])->middleware('permission:products.view')->name('product-imeis.lookup');
    Route::get('/imeis/{imei}', [ProductImeiController::class, 'show'])->middleware('permission:products.view')->name('product-imeis.show');

    Route::resource('customers', CustomerController::class)
        ->middlewareFor(['index', 'show'], 'permission:customers.view')
        ->middlewareFor(['create', 'store'], 'permission:customers.create')
        ->middlewareFor(['edit', 'update'], 'permission:customers.edit')
        ->middlewareFor('destroy', 'permission:customers.delete');
    Route::get('/debts/customers', [DebtPaymentController::class, 'customers'])->middleware('permission:customer_debts.view')->name('debts.customers');
    Route::post('/debts/customers/{customer}/payments', [DebtPaymentController::class, 'receiveCustomerPayment'])->middleware('permission:customer_debts.pay')->name('debts.customers.payments');
    Route::get('/debts/suppliers', [DebtPaymentController::class, 'suppliers'])->middleware('permission:supplier_debts.view')->name('debts.suppliers');
    Route::post('/debts/suppliers/{supplier}/payments', [DebtPaymentController::class, 'paySupplier'])->middleware('permission:supplier_debts.pay')->name('debts.suppliers.payments');
    Route::get('/api/suppliers/search', [SupplierController::class, 'search'])->middleware('permission:suppliers.view|stock_imports.view')->name('api.suppliers.search');
    Route::resource('suppliers', SupplierController::class)
        ->middlewareFor(['index', 'show'], 'permission:suppliers.view')
        ->middlewareFor(['create', 'store'], 'permission:suppliers.create')
        ->middlewareFor(['edit', 'update'], 'permission:suppliers.edit')
        ->middlewareFor('destroy', 'permission:suppliers.delete');

    /*
    |--------------------------------------------------------------------------
    | Reports (Báo cáo & Thống kê)
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:reports.view')->name('index');
        Route::get('/revenue', 'revenue')->middleware('permission:reports.view')->name('revenue');
        Route::get('/best-sellers', 'bestSellers')->middleware('permission:reports.view')->name('bestSellers');
        Route::get('/inventory', 'inventory')->middleware('permission:reports.view')->name('inventory');
        Route::get('/debts', 'debts')->middleware('permission:reports.view')->name('debts');
        Route::get('/profit', 'profit')->middleware('permission:reports.view')->name('profit');
    });
});

require __DIR__ . '/auth.php';
