<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Setting;
use App\Models\Unit;
use App\Services\CloudBackupStorage;

class SettingController extends Controller
{
    private array $bankSettingKeys = [
        'bank_bin',
        'bank_account',
        'bank_account_name',
        'bank_transfer_content',
    ];

    public function index(Request $request, BackupController $backupController, CloudBackupStorage $cloudStorage)
    {
        $user = $request->user();
        $canManageBackups = $user?->can('backups.manage') ?? false;

        return Inertia::render('Settings/Index', array_merge([
            'settings' => [
                'shop_name' => setting('shop_name', config('app.name', 'QLBH POS')),
                'currency_symbol' => setting('currency_symbol', '₫'),
                'currency_format' => setting('currency_format', 'vi-VN'),
                'app_locale' => setting('app_locale', 'vi'),
                'app_timezone' => setting('app_timezone', 'Asia/Ho_Chi_Minh'),
                'date_format' => setting('date_format', 'd/m/Y'),
                'print_paper_size' => setting('print_paper_size', 'a4'),
                'print_orientation' => setting('print_orientation', 'portrait'),
                'allow_negative_stock' => filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN),
                'bank_bin' => setting('bank_bin', ''),
                'bank_account' => setting('bank_account', ''),
                'bank_account_name' => setting('bank_account_name', ''),
                'bank_transfer_content' => setting('bank_transfer_content', 'Thanh toan don hang'),
            ],
            'can_update_bank_settings' => $this->canUpdateBankSettings($request),
            'can_edit_settings' => $user?->can('settings.edit') ?? false,
            'can_manage_payment_accounts' => $user?->can('payment_accounts.manage') ?? false,
            'can_manage_inventory_settings' => $user?->can('units.manage') ?? false,
            'can_manage_backups' => $canManageBackups,
            'active_section' => $request->query('section'),
            'units' => Unit::query()
                ->select('id', 'name', 'short_name', 'is_active')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ], $canManageBackups ? $backupController->pageData($cloudStorage) : [
            'backups' => [], 'cloud_connections' => [], 'cloud_oauth' => [],
            'restore_preview' => null,
            'schedule' => ['enabled' => false, 'frequency' => 'daily', 'time' => '02:00', 'weekday' => 1, 'monthday' => 1],
        ]));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->can('settings.edit') || $user?->can('payment_accounts.manage'), 403);

        $data = $request->validate([
            'shop_name' => ['sometimes', 'string', 'max:150'],
            'currency_symbol' => ['sometimes', 'string', 'max:12'],
            'currency_format' => ['sometimes', 'in:vi-VN,en-US'],
            'app_locale' => ['sometimes', 'in:vi,en'],
            'app_timezone' => ['sometimes', 'in:Asia/Ho_Chi_Minh,Asia/Bangkok,UTC'],
            'date_format' => ['sometimes', 'in:d/m/Y,Y-m-d,m/d/Y'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'print_paper_size' => ['sometimes', 'in:a4,a5,80mm,58mm'],
            'print_orientation' => ['sometimes', 'in:portrait,landscape'],
            'bank_bin' => ['sometimes', 'nullable', 'string', 'max:20'],
            'bank_account' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'bank_transfer_content' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($user->can('settings.edit')) {
            if (! $this->canUpdateBankSettings($request)) {
                foreach ($this->bankSettingKeys as $key) {
                    unset($data[$key]);
                }
            }
        } else {
            $data = array_intersect_key($data, array_flip($this->bankSettingKeys));
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Đã lưu cài đặt');
    }

    private function canUpdateBankSettings(Request $request): bool
    {
        $user = $request->user();

        return $user?->can('payment_accounts.manage') ?? false;
    }
}
