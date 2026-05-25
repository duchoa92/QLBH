<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerSearchController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Api\PosScanController;
use App\Http\Controllers\Api\HoldSaleController;


Route::middleware('auth:sanctum')->group(function () {

    // Quét mã vạch để tìm sản phẩm
    Route::post(
        '/pos/scan',
        [PosScanController::class, 'scan']
    );

    // Tìm kiếm sản phẩm theo tên hoặc mã vạch
    Route::get(
        '/products/search',
        [ProductSearchController::class, 'search']
    );

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
    // Xóa hóa đơn giữ
    Route::delete(
        '/hold-sales/{holdSale}',
        [HoldSaleController::class, 'destroy']
    );

});
