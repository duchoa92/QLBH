<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerFaceController;
use App\Http\Controllers\Api\DoorCameraController;
use App\Http\Middleware\DoorCameraKey;
use Illuminate\Routing\Middleware\SubstituteBindings;
use App\Http\Controllers\CustomerVisitController;
use App\Http\Controllers\Api\PosScanController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\AdministrativeUnitController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\BankTransferWebhookController;
use App\Http\Controllers\SettingController;

Route::post('/webhooks/bank-transactions', [BankTransferWebhookController::class, 'receive'])->name('bank-payments.webhook');

// Worker camera cửa (chạy nền, không đăng nhập): xác thực bằng khóa X-Camera-Key, dùng lại đúng logic so khớp/ghi lượt của POS.
Route::middleware([DoorCameraKey::class, SubstituteBindings::class])->prefix('door-camera')->group(function () {
    Route::post('/heartbeat', [DoorCameraController::class, 'heartbeat'])->middleware('throttle:120,1,door_heartbeat');
    Route::get('/config', [DoorCameraController::class, 'config'])->middleware('throttle:60,1,door_config');
    Route::post('/snapshot', [DoorCameraController::class, 'storeSnapshot'])->middleware('throttle:120,1,door_snapshot');
    Route::post('/faces', [DoorCameraController::class, 'storeFaces'])->middleware('throttle:900,1,door_faces'); // metadata nhãn nhận diện cho video WebRTC
    Route::post('/engine-metrics', [DoorCameraController::class, 'storeEngineMetrics'])->middleware('throttle:30,1,door_engine_metrics');
    Route::post('/sface-enroll/{jobId}', [DoorCameraController::class, 'completeSfaceEnrollment'])->middleware('throttle:10,1,door_sface_enroll');
    Route::post('/face-match', [CustomerFaceController::class, 'match'])->middleware('throttle:240,1');
    Route::post('/visits', [CustomerVisitController::class, 'record'])->middleware('throttle:240,1');
    Route::post('/visits/{visit}/seen', [CustomerVisitController::class, 'left'])->middleware('throttle:600,1');
    Route::post('/visits/{visit}/link', [CustomerVisitController::class, 'link'])->middleware('throttle:60,1');
});


Route::middleware(['web', 'auth'])->group(function ()
{
    Route::get('/vietqr/apps/{platform}', fn (SettingController $controller, string $platform) => response()->json($controller->vietQrApps($platform)))
        ->middleware('permission:stock_imports.create');
    Route::get('/global-search', GlobalSearchController::class);
    Route::get('/administrative-units/provinces', [AdministrativeUnitController::class, 'provinces'])
        ->middleware('permission:customers.create|customers.edit|pos.access|stock_imports.create');
    Route::get('/administrative-units/provinces/{provinceCode}/wards', [AdministrativeUnitController::class, 'wards'])
        ->middleware('permission:customers.create|customers.edit|pos.access|stock_imports.create');
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
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view|pos.access|stock_imports.create');

    Route::get('/products/{id}', [ProductController::class, 'getProductApi'])->middleware('permission:products.view|pos.access|stock_imports.create');

    // Danh sách IMEI còn trong kho của 1 sản phẩm (POS chọn IMEI)
    Route::get('/products/{id}/imeis', [ProductController::class, 'imeis'])->middleware('permission:products.view|pos.access');
    

    // Tìm kiếm khách hàng
    Route::get('/customers/search', [CustomerController::class, 'search'])->middleware('permission:customers.view|pos.access|repairs.view');
    Route::get('/customers/check-cccd', [CustomerController::class, 'checkCccd'])
        ->middleware('permission:customers.create|customers.edit|customers.view|pos.access|stock_imports.create');
    Route::get('/customers/check-phone', [CustomerController::class, 'checkPhone'])
        ->middleware('permission:customers.create|customers.edit|customers.view|pos.access|stock_imports.create');
    // Nhận diện khách bằng khuôn mặt (chỉ khách đã đồng ý): so khớp ở POS, đăng ký/xóa ở trang Khách hàng.
    Route::post('/customers/face-match', [CustomerFaceController::class, 'match'])
        ->middleware(['permission:pos.access', 'throttle:60,1']);
    Route::post('/customers/{customer}/face-feedback', [CustomerFaceController::class, 'feedback'])
        ->middleware(['permission:pos.access', 'throttle:30,1']);
    // Ghi nhận lượt khách trước camera POS (khách quen + khách lạ), liên kết khách lạ khi đã có thông tin.
    Route::post('/customers/visits', [CustomerVisitController::class, 'record'])
        ->middleware(['permission:pos.access', 'throttle:60,1']);
    Route::get('/customers/visits/present', [CustomerVisitController::class, 'present'])
        ->middleware(['permission:pos.access', 'throttle:120,1']);
    Route::post('/customers/visits/{visit}/served', [CustomerVisitController::class, 'served'])
        ->middleware(['permission:pos.access', 'throttle:60,1']);
    Route::post('/customers/visits/{visit}/left', [CustomerVisitController::class, 'left'])
        ->middleware(['permission:pos.access', 'throttle:120,1']);
    Route::post('/customers/visits/{visit}/link', [CustomerVisitController::class, 'link'])
        ->middleware(['permission:pos.access|customers.edit', 'throttle:30,1']);
    Route::get('/customers/door-camera/live', [DoorCameraController::class, 'live'])
        ->middleware(['permission:pos.access|customers.view', 'throttle:30,1,door_live']);
    Route::get('/customers/door-camera/live-faces', [DoorCameraController::class, 'liveFaces'])
        ->middleware(['permission:pos.access|customers.view', 'throttle:240,1,door_live_faces']);
    Route::get('/customers/door-camera/engine-metrics', [DoorCameraController::class, 'engineMetrics'])
        ->middleware(['permission:settings.edit', 'throttle:60,1,door_engine_metrics_view']);
    Route::post('/customers/door-camera/webrtc-offer', [DoorCameraController::class, 'webrtcOffer'])
        ->middleware(['permission:pos.access|customers.view', 'throttle:30,1,door_webrtc']);
    Route::get('/customers/face-cctv-snapshot', [CustomerFaceController::class, 'cctvSnapshot'])
        ->middleware(['permission:customers.edit', 'throttle:20,1']);
    Route::get('/customers/{customer}/face', [CustomerFaceController::class, 'status'])
        ->middleware('permission:customers.view|customers.edit');
    Route::post('/customers/{customer}/face', [CustomerFaceController::class, 'store'])
        ->middleware('permission:customers.edit');
    Route::post('/customers/{customer}/face-sface-enrollment', [CustomerFaceController::class, 'requestSfaceEnrollment'])
        ->middleware(['permission:customers.edit', 'throttle:10,1,customer_sface_enrollment']);
    Route::delete('/customers/{customer}/face', [CustomerFaceController::class, 'destroy'])
        ->middleware('permission:customers.edit');

    // Thêm khách hàng mới
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create|pos.access|stock_imports.create');

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
    )->middleware('permission:suppliers.view|stock_imports.view|stock_imports.create');


});
