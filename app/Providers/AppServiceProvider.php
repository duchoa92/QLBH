<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\App;

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

        Inertia::share([
            'settings' => fn () => [
                'currency_symbol' => setting('currency_symbol', '₫'),
                'currency_format' => setting('currency_format', 'vi-VN'),
                'app_locale' => setting('app_locale', 'vi'),
                'bank_bin' => setting('bank_bin', ''),
                'bank_account' => setting('bank_account', ''),
                'bank_account_name' => setting('bank_account_name', ''),
                'bank_transfer_content' => setting('bank_transfer_content', 'Thanh toan don hang'),
            ]
        ]);
    }
}
