<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerSearchController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductSearchController;


Route::get(
    '/products/search',
    [ProductSearchController::class, 'search']
);

Route::get('/customers/search', [CustomerController::class, 'search']);
Route::post('/customers', [CustomerController::class, 'store']);

Route::get('/test', function () {
    return response()->json([
        'status' => 'ok'
    ]);
});