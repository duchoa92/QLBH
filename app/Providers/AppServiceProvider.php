<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\App;
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

        App::setLocale(setting('app_locale', 'vi'));
        $timezone = setting('app_timezone', config('app.timezone', 'Asia/Ho_Chi_Minh'));
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        Inertia::share([
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
                'purchase_contract_title' => setting('purchase_contract_title', DefaultPurchaseContract::TITLE),
                'purchase_contract_content' => setting('purchase_contract_content', DefaultPurchaseContract::CONTENT),
                'allow_negative_stock' => filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN),
                'inventory_alert_enabled' => filter_var(setting('inventory_alert_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'inventory_alert_threshold' => (int) setting('inventory_alert_threshold', 5),
                'stock_import_vat_default' => filter_var(setting('stock_import_vat_default', false), FILTER_VALIDATE_BOOLEAN),
                'work_shift_auto_open_enabled' => filter_var(setting('work_shift_auto_open_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'bank_bin' => setting('bank_bin', ''),
                'bank_account' => setting('bank_account', ''),
                'bank_account_name' => setting('bank_account_name', ''),
                'bank_accounts' => collect(json_decode((string) setting('bank_accounts', '[]'), true) ?: [])->map(function (array $account): array {
                    $apiKey = (string) ($account['payment_api_key'] ?? '');
                    $apiAccessKey = (string) ($account['payment_api_access_key'] ?? '');
                    $apiSecretKey = (string) ($account['payment_api_secret_key'] ?? '');
                    $webhookSecret = (string) ($account['payment_webhook_secret'] ?? '');
                    unset($account['payment_api_key'], $account['payment_api_access_key'], $account['payment_api_secret_key'], $account['payment_webhook_secret']);
                    $provider = strtolower((string) ($account['payment_provider'] ?? ''));
                    $account['payment_api_key_configured'] = filled($apiKey);
                    $account['payment_api_access_key_configured'] = filled($apiAccessKey);
                    $account['payment_api_secret_key_configured'] = filled($apiSecretKey);
                    $account['payment_webhook_secret_configured'] = filled($webhookSecret);
                    $account['payment_auto_enabled'] = $provider === 'apipay'
                        ? (filled($webhookSecret) && filled($apiAccessKey) && filled($apiSecretKey) && filled($account['payment_provider_account_id'] ?? null))
                        : ($provider === 'sepay' && (filled($apiKey) || filled($webhookSecret)));
                    return $account;
                })->values()->all(),
                'bank_transfer_content' => setting('bank_transfer_content', 'Thanh toan don hang'),
                'bank_qr_theme' => setting('bank_qr_theme', 'classic'),
                'payment_voice_language' => setting('payment_voice_language', 'vi-VN'),
                'payment_voice_enabled' => filter_var(setting('payment_voice_enabled', true), FILTER_VALIDATE_BOOLEAN),
            ]
        ]);
    }
}
