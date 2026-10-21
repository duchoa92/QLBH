<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Setting;
use App\Models\Unit;
use App\Services\CloudBackupStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class SettingController extends Controller
{
    private array $bankSettingKeys = [
        'bank_bin',
        'bank_account',
        'bank_account_name',
        'bank_accounts',
        'bank_transfer_content',
        'bank_qr_theme',
        'payment_voice_language',
        'payment_voice_enabled',
    ];

    public function index(Request $request, BackupController $backupController, CloudBackupStorage $cloudStorage)
    {
        $user = $request->user();
        $canManageBackups = $user?->can('backups.manage') ?? false;
        $banks = $this->vietQrBanks();

        return Inertia::render('Settings/Index', array_merge([
            'banks' => $banks,
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
                'inventory_alert_enabled' => filter_var(setting('inventory_alert_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'inventory_alert_threshold' => (int) setting('inventory_alert_threshold', 5),
                'bank_bin' => setting('bank_bin', ''),
                'bank_account' => setting('bank_account', ''),
                'bank_account_name' => setting('bank_account_name', ''),
                'bank_accounts' => collect(json_decode((string) setting('bank_accounts', '[]'), true) ?: [])->map(function (array $account): array {
                    $apiKey = (string) ($account['payment_api_key'] ?? '');
                    $apiAccessKey = (string) ($account['payment_api_access_key'] ?? '');
                    $apiSecretKey = (string) ($account['payment_api_secret_key'] ?? '');
                    $webhookSecret = (string) ($account['payment_webhook_secret'] ?? '');
                    unset($account['payment_api_key'], $account['payment_api_access_key'], $account['payment_api_secret_key'], $account['payment_webhook_secret']);
                    $account['payment_api_key_configured'] = filled($apiKey);
                    $account['payment_api_access_key_configured'] = filled($apiAccessKey);
                    $account['payment_api_secret_key_configured'] = filled($apiSecretKey);
                    $account['payment_webhook_secret_configured'] = filled($webhookSecret);
                    $provider = strtolower((string) ($account['payment_provider'] ?? ''));
                    $account['payment_auto_enabled'] = $provider === 'apipay'
                        ? (filled($webhookSecret) && filled($apiAccessKey) && filled($apiSecretKey) && filled($account['payment_provider_account_id'] ?? null))
                        : ($provider === 'sepay' && (filled($apiKey) || filled($webhookSecret)));
                    return $account;
                })->values()->all(),
                'bank_transfer_content' => setting('bank_transfer_content', 'Thanh toan don hang'),
                'bank_qr_theme' => setting('bank_qr_theme', 'classic'),
                'payment_voice_language' => setting('payment_voice_language', 'vi-VN'),
                'payment_voice_enabled' => filter_var(setting('payment_voice_enabled', true), FILTER_VALIDATE_BOOLEAN),
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
        abort_unless($user?->can('settings.edit') || $user?->can('payment_accounts.manage') || $user?->can('units.manage'), 403);

        $data = $request->validate([
            'shop_name' => ['sometimes', 'string', 'max:150'],
            'currency_symbol' => ['sometimes', 'string', 'max:12'],
            'currency_format' => ['sometimes', 'in:vi-VN,en-US'],
            'app_locale' => ['sometimes', 'in:vi,en'],
            'app_timezone' => ['sometimes', 'in:Asia/Ho_Chi_Minh,Asia/Bangkok,UTC'],
            'date_format' => ['sometimes', 'in:d/m/Y,Y-m-d,m/d/Y'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'inventory_alert_enabled' => ['sometimes', 'boolean'],
            'inventory_alert_threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'print_paper_size' => ['sometimes', 'in:a4,a5,80mm,58mm'],
            'print_orientation' => ['sometimes', 'in:portrait,landscape'],
            'bank_bin' => ['sometimes', 'nullable', 'string', 'max:20'],
            'bank_account' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'bank_accounts' => ['sometimes', 'array', 'max:10'],
            'bank_accounts.*.id' => ['nullable', 'string', 'max:80'],
            'bank_accounts.*.bank_bin' => ['required_with:bank_accounts.*.bank_account', 'nullable', 'string', 'max:20'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.bank_account' => ['required_with:bank_accounts.*.bank_bin', 'nullable', 'string', 'max:50'],
            'bank_accounts.*.bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.transfer_content_mode' => ['nullable', 'in:default,invoice'],
            'bank_accounts.*.is_default' => ['sometimes', 'boolean'],
            'bank_accounts.*.payment_provider' => ['nullable', 'in:none,apipay,sepay'],
            'bank_accounts.*.payment_provider_account_id' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.payment_api_key' => ['nullable', 'string', 'max:2000'],
            'bank_accounts.*.payment_api_access_key' => ['nullable', 'string', 'max:2000'],
            'bank_accounts.*.payment_api_secret_key' => ['nullable', 'string', 'max:2000'],
            'bank_accounts.*.payment_webhook_secret' => ['nullable', 'string', 'max:2000'],
            'bank_transfer_content' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_qr_theme' => ['sometimes', 'in:classic,emerald,indigo,blue,rose'],
            'payment_voice_language' => ['sometimes', 'in:vi-VN,en-US'],
            'payment_voice_enabled' => ['sometimes', 'boolean'],
        ]);

        if ($user->can('settings.edit')) {
            if (! $this->canUpdateBankSettings($request)) {
                foreach ($this->bankSettingKeys as $key) {
                    unset($data[$key]);
                }
            }
        } elseif ($user->can('units.manage')) {
            $data = array_intersect_key($data, array_flip(['allow_negative_stock', 'inventory_alert_enabled', 'inventory_alert_threshold']));
        } else {
            $data = array_intersect_key($data, array_flip($this->bankSettingKeys));
        }

        foreach ($data as $key => $value) {
            if ($key !== 'bank_accounts') {
                Setting::set($key, $value);
            }
        }

        if (array_key_exists('bank_accounts', $data)) {
            $existingAccounts = collect(json_decode((string) setting('bank_accounts', '[]'), true) ?: [])->keyBy(fn ($account) => (string) ($account['id'] ?? ''));
            $accounts = collect($data['bank_accounts'])
                ->filter(fn ($account) => filled($account['bank_bin'] ?? null) && filled($account['bank_account'] ?? null))
                ->values()
                ->map(function ($account, $index) use ($existingAccounts): array {
                    $id = (string) ($account['id'] ?? ('bank-' . ($index + 1)));
                    $existing = $existingAccounts->get($id, []);
                    $entry = [
                        'id' => $id,
                        'bank_bin' => (string) $account['bank_bin'],
                        'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                        'bank_account' => trim((string) $account['bank_account']),
                        'bank_account_name' => trim((string) ($account['bank_account_name'] ?? '')),
                        'transfer_content_mode' => ($account['transfer_content_mode'] ?? 'default') === 'invoice' ? 'invoice' : 'default',
                        'is_default' => (bool) ($account['is_default'] ?? false),
                        'payment_provider' => in_array($account['payment_provider'] ?? '', ['apipay', 'sepay'], true) ? $account['payment_provider'] : '',
                        'payment_provider_account_id' => trim((string) ($account['payment_provider_account_id'] ?? '')),
                    ];
                    foreach (['payment_api_key', 'payment_api_access_key', 'payment_api_secret_key', 'payment_webhook_secret'] as $secretKey) {
                        $incoming = trim((string) ($account[$secretKey] ?? ''));
                        $entry[$secretKey] = $incoming !== ''
                            ? Crypt::encryptString($incoming)
                            : (string) ($existing[$secretKey] ?? '');
                    }
                    return $entry;
                });

            if ($accounts->isNotEmpty()) {
                $defaultIndex = $accounts->search(fn ($account) => $account['is_default']);
                $defaultIndex = $defaultIndex === false ? 0 : $defaultIndex;
                $accounts = $accounts->map(fn ($account, $index) => [
                    ...$account,
                    'is_default' => $index === $defaultIndex,
                ]);
                $defaultAccount = $accounts[$defaultIndex];
            } else {
                $defaultAccount = null;
            }

            Setting::set('bank_accounts', json_encode($accounts->all(), JSON_UNESCAPED_UNICODE));
            Setting::set('bank_bin', $defaultAccount['bank_bin'] ?? '');
            Setting::set('bank_account', $defaultAccount['bank_account'] ?? '');
            Setting::set('bank_account_name', $defaultAccount['bank_account_name'] ?? '');
        } elseif (array_intersect(['bank_bin', 'bank_account', 'bank_account_name'], array_keys($data))) {
            $legacyAccount = [
                'id' => 'legacy-default',
                'bank_bin' => (string) ($data['bank_bin'] ?? setting('bank_bin', '')),
                'bank_name' => '',
                'bank_account' => (string) ($data['bank_account'] ?? setting('bank_account', '')),
                'bank_account_name' => (string) ($data['bank_account_name'] ?? setting('bank_account_name', '')),
                'is_default' => true,
            ];
            Setting::set('bank_accounts', filled($legacyAccount['bank_bin']) && filled($legacyAccount['bank_account'])
                ? json_encode([$legacyAccount], JSON_UNESCAPED_UNICODE)
                : '[]');
        }

        return back()->with('success', 'Đã lưu cài đặt');
    }

    private function canUpdateBankSettings(Request $request): bool
    {
        $user = $request->user();

        return $user?->can('payment_accounts.manage') ?? false;
    }

    private function vietQrBanks(): array
    {
        $freshCacheKey = 'settings.vietqr_banks';
        $staleCacheKey = 'settings.vietqr_banks.stale';
        $banks = Cache::get($freshCacheKey);

        if (is_array($banks)) {
            return $banks;
        }

        try {
            $response = Http::timeout(5)->get('https://api.vietqr.io/v2/banks');
            $rows = $response->successful() ? $response->json('data') : null;

            if (! is_array($rows)) {
                throw new \UnexpectedValueException('VietQR bank list response is invalid.');
            }

            $banks = collect($rows)
                ->filter(fn ($bank) => is_array($bank) && filled($bank['bin'] ?? null))
                ->map(fn ($bank) => [
                    'bin' => (string) $bank['bin'],
                    'code' => (string) ($bank['code'] ?? ''),
                    'shortName' => (string) ($bank['shortName'] ?? $bank['code'] ?? ''),
                    'name' => (string) ($bank['name'] ?? ''),
                ])
                ->values()
                ->all();

            if (! $banks) {
                throw new \UnexpectedValueException('VietQR bank list is empty.');
            }

            Cache::put($freshCacheKey, $banks, now()->addDay());
            Cache::put($staleCacheKey, $banks, now()->addDays(90));

            return $banks;
        } catch (\Throwable $exception) {
            report($exception);

            return Cache::get($staleCacheKey, []);
        }
    }

}
