<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerSearchController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Api\PosScanController;
use App\Http\Controllers\Api\HoldSaleController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductApiController;


Route::middleware(['web', 'auth'])->group(function () {

// Quét mã vạch để tìm sản phẩm
Route::post(
    '/pos/scan',
    [PosScanController::class, 'scan']
);

// Tìm kiếm sản phẩm theo mã vạch
Route::get(
    '/products/barcode',
    [
        ProductApiController::class,
        'findByBarcode',
    ]
);

// Tìm kiếm sản phẩm theo tên hoặc mã vạch
Route::get(
    '/products/search',
    [ProductSearchController::class, 'search']
);

// Lấy danh sách sản phẩm
Route::get('/products', [ProductController::class, 'index']);


// Tìm kiếm khách hàng
Route::get('/customers/search', [CustomerController::class, 'search']);
// Thêm khách hàng mới
Route::post('/customers', [CustomerController::class, 'store']);

// Danh sách hóa đơn giữ
Route::get(
    '/hold-sales',
    [HoldSaleController::class, 'index']
);
// Lưu hóa đơn giữ
Route::post(
    '/hold-sales',
    [HoldSaleController::class, 'store']
);

// Lấy chi tiết hóa đơn giữ
Route::get(
    '/hold-sales/{holdSale}',
    [HoldSaleController::class, 'show']
);

// Xóa hóa đơn giữ
Route::delete(
    '/hold-sales/{holdSale}',
    [HoldSaleController::class, 'destroy']
);

// Lấy danh sách danh mục
Route::get('/categories', [CategoryController::class, 'index']);


// lấy chi tiết Nợ của khách hàng
Route::get(
    '/customers/{customer}/debts',
    [CustomerController::class, 'debts']
);




});
