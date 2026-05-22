<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerSearchController;

Route::get('/customers/search', CustomerSearchController::class);

Route::get('/test', function () {
    return response()->json([
        'status' => 'ok'
    ]);
});