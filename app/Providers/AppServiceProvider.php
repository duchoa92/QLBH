<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\App;
use App\Support\DataVersion;
use App\Support\DefaultPurchaseContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Theo dõi mọi thay đổi dữ liệu để các trang tự cập nhật (xem App\Support\DataVersion).
        DataVersion::listen();

        App::setLocale(setting('app_locale', 'vi'));
        $timezone = setting('app_timezone', config('app.timezone', 'Asia/Ho_Chi_Minh'));
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        Inertia::share([
            // Mốc phiên bản dữ liệu lúc dựng trang: trình duyệt dùng làm mốc so sánh đầu tiên.
            'data_versions' => fn () => DataVersion::all(),
            'vietqr_banks' => fn () => app(\App\Http\Controllers\SettingController::class)->vietQrBanks(),
            'settings' => fn () => [
                'shop_name' => setting('shop_name', config('app.name', 'QLBH POS')),
                'currency_symbol' => setting('currency_symbol', '₫'),
                'currency_format' => setting('currency_format', 'vi-VN'),
                'app_locale' => setting('app_locale', 'vi'),
                'app_timezone' => setting('app_timezone', 'Asia/Ho_Chi_Minh'),
                'date_format' => setting('date_format', 'd/m/Y'),
                'print_paper_size' => setting('print_paper_size', 'a4'),
                'print_orientation' => setting('print_orientation', 'portrait'),
                'print_template' => setting('print_template', 'standard'),
                'print_shop_subtitle' => setting('print_shop_subtitle', ''),
                'print_shop_address' => setting('print_shop_address', ''),
                'print_shop_phone' => setting('print_shop_phone', ''),
                'label_template' => \App\Support\LabelTemplate::normalize(setting('label_template')),
                'purchase_contract_title' => setting('purchase_contract_title', DefaultPurchaseContract::TITLE),
                'purchase_contract_content' => setting('purchase_contract_content', DefaultPurchaseContract::CONTENT),
                'allow_negative_stock' => filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN),
                'inventory_alert_enabled' => filter_var(setting('inventory_alert_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'inventory_alert_threshold' => (int) setting('inventory_alert_threshold', 5),
                'stock_import_vat_default' => filter_var(setting('stock_import_vat_default', false), FILTER_VALIDATE_BOOLEAN),
                'stock_import_vat_rate' => min(100, max(0, (float) setting('stock_import_vat_rate', 10))),
                'pos_vat_default_enabled' => filter_var(setting('pos_vat_default_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'pos_vat_default_scope' => in_array(setting('pos_vat_default_scope', 'all'), ['all', 'vat_products'], true) ? setting('pos_vat_default_scope', 'all') : 'all',
                'pos_vat_rate' => min(100, max(0, (float) setting('pos_vat_rate', 10))),
                'pos_vat_used_enabled' => filter_var(setting('pos_vat_used_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'pos_vat_used_default' => filter_var(setting('pos_vat_used_default', false), FILTER_VALIDATE_BOOLEAN),
                'pos_cost_price_suggestion_enabled' => filter_var(setting('pos_cost_price_suggestion_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'work_shift_opening_count_enabled' => filter_var(setting('work_shift_opening_count_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'work_shift_auto_open_enabled' => filter_var(setting('work_shift_auto_open_enabled', true), FILTER_VALIDATE_BOOLEAN),
                // Nhận diện khuôn mặt khách: mặc định TẮT, chủ cửa hàng bật ở Thiết lập → Khách hàng.
                'face_recognition_enabled' => filter_var(setting('face_recognition_enabled', false), FILTER_VALIDATE_BOOLEAN),
                // Khi nhận ra khách bằng khuôn mặt: hiện thêm hộp thông báo chi tiết (mặc định TẮT, giữ cách hỏi ngay trong ô khách).
                'face_notify_modal_enabled' => filter_var(setting('face_notify_modal_enabled', false), FILTER_VALIDATE_BOOLEAN),
                // Ghi nhận lượt khách trước camera POS (kể cả khách lạ) để thống kê; mặc định TẮT.
                'face_visit_tracking_enabled' => filter_var(setting('face_visit_tracking_enabled', false), FILTER_VALIDATE_BOOLEAN),
                // POS không mở camera mà đọc "ai đang ở cửa" từ máy chủ (worker camera cửa lo nhận diện); mặc định TẮT.
                'face_door_camera_enabled' => filter_var(setting('face_door_camera_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'face_visit_retention_days' => min(365, max(1, (int) setting('face_visit_retention_days', 30))),
                'bank_bin' => setting('bank_bin', ''),
                'bank_account' => setting('bank_account', ''),
                'bank_account_name' => setting('bank_account_name', ''),
                'bank_accounts' => \App\Http\Controllers\SettingController::publicBankAccounts(),
                'bank_transfer_content' => setting('bank_transfer_content', 'Thanh toan don hang'),
                'bank_qr_theme' => setting('bank_qr_theme', 'classic'),
                'payment_voice_language' => setting('payment_voice_language', 'vi-VN'),
                'payment_voice_enabled' => filter_var(setting('payment_voice_enabled', true), FILTER_VALIDATE_BOOLEAN),
            ]
        ]);
    }
}
