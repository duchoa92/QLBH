<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PosScanController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\SupplierController;


Route::middleware(['web', 'auth'])->group(function ()
{
    Route::get('/global-search', GlobalSearchController::class);
    // Dữ liệu nền cho các ô chọn, luôn lấy mới mà không tải lại trang.
    Route::get('/reference-data', [ReferenceDataController::class, 'index'])
        ->middleware('permission:products.view|customers.view|suppliers.view|stock_imports.view|repairs.view|pos.access|users.create|categories.view');


    // Quét mã vạch để tìm sản phẩm
    Route::post(
        '/pos/scan',
        [PosScanController::class, 'scan']
    )->middleware('permission:pos.access');

    // Tìm kiếm sản phẩm theo mã vạch
    Route::get(
        '/products/barcode',
        [
            ProductApiController::class,
            'findByBarcode',
        ]
    )->middleware('permission:products.view|pos.access');

    // Lấy danh sách sản phẩm
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view|pos.access');

    Route::get('/products/{id}', [ProductController::class, 'getProductApi'])->middleware('permission:products.view|pos.access');

    // Danh sách IMEI còn trong kho của 1 sản phẩm (POS chọn IMEI)
    Route::get('/products/{id}/imeis', [ProductController::class, 'imeis'])->middleware('permission:products.view|pos.access');
    

    // Tìm kiếm khách hàng
    Route::get('/customers/search', [CustomerController::class, 'search'])->middleware('permission:customers.view|pos.access|repairs.view');
    // Thêm khách hàng mới
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create|pos.access');

    // Lấy danh sách danh mục
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view|products.view|pos.access');


    // lấy chi tiết Nợ của khách hàng
    Route::get(
        '/customers/{customer}/debts',
        [CustomerController::class, 'debts']
    )->middleware('permission:customer_debts.view|pos.access');

    Route::get(
        '/sales',
        [
            SaleController::class,
            'index'
        ]
    )->middleware('permission:sales.view|pos.access');

    // Hiện hóa đơn bán
    Route::get(
        '/sales/{sale}',
        [
            SaleController::class,
            'show'
        ]
    )->middleware('permission:sales.view|pos.access');
    
    // Nhà cung cấp
    Route::get(
        '/suppliers/search',
        [SupplierController::class, 'search']
    )->middleware('permission:suppliers.view|stock_imports.view');


});
