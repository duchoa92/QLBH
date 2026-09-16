<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Setting;
use App\Models\Unit;

class SettingController extends Controller
{
    private array $bankSettingKeys = [
        'bank_bin',
        'bank_account',
        'bank_account_name',
        'bank_transfer_content',
    ];

    public function index()
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'shop_name' => setting('shop_name'),
                'currency_symbol' => setting('currency_symbol', '₫'),
                'currency_format' => setting('currency_format', 'vi-VN'),
                'app_locale' => setting('app_locale', 'vi'),
                'allow_negative_stock' => setting('allow_negative_stock', false),
                'bank_bin' => setting('bank_bin', ''),
                'bank_account' => setting('bank_account', ''),
                'bank_account_name' => setting('bank_account_name', ''),
                'bank_transfer_content' => setting('bank_transfer_content', 'Thanh toan don hang'),
            ],
            'can_update_bank_settings' => $this->canUpdateBankSettings($request = request()),
            'units' => Unit::query()
                ->select('id', 'name', 'short_name', 'is_active')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->all();

        if (! $this->canUpdateBankSettings($request)) {
            foreach ($this->bankSettingKeys as $key) {
                unset($data[$key]);
            }
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Đã lưu cài đặt');
    }

    private function canUpdateBankSettings(Request $request): bool
    {
        $user = $request->user();

        return $user
            && (
                $user->hasRole('Admin')
                || $user->hasRole('admin')
                || $user->hasRole('Super Admin')
            );
    }
}
