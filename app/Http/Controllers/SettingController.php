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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\DefaultPurchaseContract;
use App\Support\DoorCameraAccess;
use Symfony\Component\Process\Process;

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

    /** Các cài đặt của mục "Bán hàng và kho hàng" (quyền units.manage). */
    private array $inventorySettingKeys = [
        'allow_negative_stock',
        'inventory_alert_enabled',
        'inventory_alert_threshold',
        'stock_import_vat_default',
        'stock_import_vat_rate',
        'pos_vat_default_enabled',
        'pos_vat_default_scope',
        'pos_vat_rate',
        'pos_vat_used_enabled',
        'pos_vat_used_default',
        'work_shift_opening_count_enabled',
        'work_shift_auto_open_enabled',
        'pos_cost_price_suggestion_enabled',
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
                'customer_points_enabled' => filter_var(setting('customer_points_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'face_recognition_enabled' => filter_var(setting('face_recognition_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'face_notify_modal_enabled' => filter_var(setting('face_notify_modal_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'face_visit_tracking_enabled' => filter_var(setting('face_visit_tracking_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'face_door_camera_enabled' => filter_var(setting('face_door_camera_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'face_visit_retention_days' => min(365, max(1, (int) setting('face_visit_retention_days', 30))),
                'face_camera_brand' => in_array(setting('face_camera_brand', 'custom'), ['hikvision', 'dahua', 'imou', 'ezviz', 'reolink', 'uniview', 'vigi', 'custom'], true) ? setting('face_camera_brand', 'custom') : 'custom',
                'face_camera_host' => setting('face_camera_host', ''),
                'face_camera_channel' => min(64, max(1, (int) setting('face_camera_channel', 1))),
                'face_cctv_snapshot_url' => setting('face_cctv_snapshot_url', ''),
                'face_cctv_view_rtsp_url' => setting('face_cctv_view_rtsp_url', ''),
                'face_cctv_auth' => in_array(setting('face_cctv_auth', 'none'), ['none', 'basic', 'digest'], true) ? setting('face_cctv_auth', 'none') : 'none',
                'face_cctv_username' => setting('face_cctv_username', ''),
                // Không trả mật khẩu về trình duyệt, chỉ cho biết đã lưu hay chưa.
                'face_cctv_has_password' => filled(setting('face_cctv_password')),
                // Multi-camera is the canonical configuration. The legacy
                // single-camera keys above remain present for older screens.
                'face_cameras' => DoorCameraAccess::publicCameras(),
                ...\App\Support\FaceTuning::all(),
                'customer_points_amount_per_point' => max(1, (int) setting('customer_points_amount_per_point', 10000)),
                'customer_points_basis' => in_array(setting('customer_points_basis', 'revenue'), ['revenue', 'profit'], true) ? setting('customer_points_basis', 'revenue') : 'revenue',
                'customer_points_debt_enabled' => filter_var(setting('customer_points_debt_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'customer_points_debt_mode' => in_array(setting('customer_points_debt_mode', 'same'), ['none', 'same', 'half', 'custom'], true) ? setting('customer_points_debt_mode', 'same') : 'same',
                'customer_points_debt_percent' => min(100, max(1, (int) setting('customer_points_debt_percent', 50))),
                'bank_bin' => setting('bank_bin', ''),
                'bank_account' => setting('bank_account', ''),
                'bank_account_name' => setting('bank_account_name', ''),
                'bank_accounts' => self::publicBankAccounts(),
                'bank_transfer_content' => setting('bank_transfer_content', 'Thanh toan don hang'),
                'bank_qr_theme' => setting('bank_qr_theme', 'classic'),
                'payment_voice_language' => setting('payment_voice_language', 'vi-VN'),
                'payment_voice_enabled' => filter_var(setting('payment_voice_enabled', true), FILTER_VALIDATE_BOOLEAN),
            ],
            'purchase_contract_defaults' => ['title' => DefaultPurchaseContract::TITLE, 'content' => DefaultPurchaseContract::CONTENT],
            'can_update_bank_settings' => $this->canUpdateBankSettings($request),
            'can_edit_settings' => $user?->can('settings.edit') ?? false,
            // Khóa camera tự sinh + địa chỉ máy chủ để hiện lệnh cài worker (chỉ người được sửa cài đặt).
            'door_camera' => $user?->can('settings.edit') ? DoorCameraSettingsController::info() : null,
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
            'shop_name' => ['sometimes', 'required', 'string', 'max:150'],
            'currency_symbol' => ['sometimes', 'required', 'string', 'max:12'],
            'currency_format' => ['sometimes', 'in:vi-VN,en-US'],
            'app_locale' => ['sometimes', 'in:vi,en'],
            'app_timezone' => ['sometimes', 'in:Asia/Ho_Chi_Minh,Asia/Bangkok,UTC'],
            'date_format' => ['sometimes', 'in:d/m/Y,Y-m-d,m/d/Y'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'inventory_alert_enabled' => ['sometimes', 'boolean'],
            'inventory_alert_threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'stock_import_vat_default' => ['sometimes', 'boolean'],
            'stock_import_vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'pos_vat_default_enabled' => ['sometimes', 'boolean'],
            'pos_vat_default_scope' => ['sometimes', 'in:all,vat_products'],
            'pos_vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'pos_vat_used_enabled' => ['sometimes', 'boolean'],
            'pos_vat_used_default' => ['sometimes', 'boolean'],
            'pos_cost_price_suggestion_enabled' => ['sometimes', 'boolean'],
            'work_shift_opening_count_enabled' => ['sometimes', 'boolean'],
            'work_shift_auto_open_enabled' => ['sometimes', 'boolean'],
            'customer_points_enabled' => ['sometimes', 'boolean'],
            'face_recognition_enabled' => ['sometimes', 'boolean'],
            'face_notify_modal_enabled' => ['sometimes', 'boolean'],
            'face_visit_tracking_enabled' => ['sometimes', 'boolean'],
            'face_door_camera_enabled' => ['sometimes', 'boolean'],
            'face_visit_retention_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'face_camera_brand' => ['sometimes', 'in:hikvision,dahua,imou,ezviz,reolink,uniview,vigi,custom'],
            'face_camera_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'face_camera_channel' => ['sometimes', 'integer', 'min:1', 'max:64'],
            'face_cctv_snapshot_url' => [
                'sometimes', 'nullable', 'string', 'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && self::parseCameraUrl((string) $value) === null) {
                        $fail('Địa chỉ camera phải bắt đầu bằng http://, https:// hoặc rtsp:// kèm địa chỉ máy (ví dụ rtsp://192.168.1.50:554/Streaming/Channels/101).');
                    }
                },
            ],
            'face_cctv_view_rtsp_url' => [
                'sometimes', 'nullable', 'string', 'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! preg_match('#^rtsps?://#i', trim((string) $value))) {
                        $fail('Luồng xem trực tiếp phải là URL rtsp:// hoặc rtsps:// (ưu tiên sub-stream H.264).');
                    }
                },
            ],
            ...\App\Support\FaceTuning::rules(),
            'face_cctv_auth' => ['sometimes', 'in:none,basic,digest'],
            'face_cctv_username' => ['sometimes', 'nullable', 'string', 'max:100'],
            'face_cctv_password' => ['sometimes', 'nullable', 'string', 'max:200'],
            'face_cameras' => ['sometimes', 'array', 'max:8'],
            'face_cameras.*.id' => ['required_with:face_cameras', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_-]{1,63}$/', 'distinct'],
            'face_cameras.*.name' => ['required_with:face_cameras', 'string', 'max:120'],
            'face_cameras.*.enabled' => ['sometimes', 'boolean'],
            'face_cameras.*.brand' => ['nullable', 'in:hikvision,dahua,imou,ezviz,reolink,uniview,vigi,custom'],
            'face_cameras.*.host' => ['nullable', 'string', 'max:255'],
            'face_cameras.*.channel' => ['nullable', 'integer', 'min:1', 'max:64'],
            'face_cameras.*.snapshot_url' => [
                'nullable', 'string', 'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && self::parseCameraUrl((string) $value) === null) {
                        $fail('Địa chỉ camera phải bắt đầu bằng http://, https:// hoặc rtsp:// kèm địa chỉ máy.');
                    }
                },
            ],
            'face_cameras.*.view_rtsp_url' => [
                'nullable', 'string', 'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! preg_match('#^rtsps?://#i', trim((string) $value))) {
                        $fail('Luồng AI và xem trực tiếp phải là URL rtsp:// hoặc rtsps://.');
                    }
                },
            ],
            'face_cameras.*.auth' => ['nullable', 'in:none,basic,digest'],
            'face_cameras.*.username' => ['nullable', 'string', 'max:100'],
            'face_cameras.*.password' => ['nullable', 'string', 'max:200'],
            'customer_points_amount_per_point' => ['sometimes', 'integer', 'min:1', 'max:1000000000'],
            'customer_points_basis' => ['sometimes', 'in:revenue,profit'],
            'customer_points_debt_enabled' => ['sometimes', 'boolean'],
            'customer_points_debt_mode' => ['sometimes', 'in:none,same,half,custom'],
            'customer_points_debt_percent' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'print_paper_size' => ['sometimes', 'in:a4,a5,80mm,58mm'],
            'print_orientation' => ['sometimes', 'in:portrait,landscape'],
            'print_template' => ['sometimes', 'in:standard,compact'],
            'print_shop_subtitle' => ['sometimes', 'nullable', 'string', 'max:150'],
            'print_shop_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'print_shop_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'label_template' => ['sometimes', 'array'],
            'label_template.layout' => ['nullable', 'in:sheet,roll'],
            'label_template.width_mm' => ['nullable', 'numeric', 'min:20', 'max:150'],
            'label_template.height_mm' => ['nullable', 'numeric', 'min:10', 'max:100'],
            'label_template.gap_mm' => ['nullable', 'numeric', 'min:0', 'max:15'],
            'label_template.margin_mm' => ['nullable', 'numeric', 'min:0', 'max:25'],
            'label_template.border' => ['nullable', 'in:none,dashed,solid'],
            'label_template.align' => ['nullable', 'in:left,center'],
            'label_template.show_shop' => ['nullable', 'boolean'],
            'label_template.show_name' => ['nullable', 'boolean'],
            'label_template.show_variant' => ['nullable', 'boolean'],
            'label_template.show_code' => ['nullable', 'boolean'],
            'label_template.show_price' => ['nullable', 'boolean'],
            'label_template.show_barcode' => ['nullable', 'boolean'],
            'label_template.name_size' => ['nullable', 'numeric', 'min:5', 'max:24'],
            'label_template.code_size' => ['nullable', 'numeric', 'min:5', 'max:20'],
            'label_template.price_size' => ['nullable', 'numeric', 'min:5', 'max:28'],
            'label_template.barcode_height_mm' => ['nullable', 'numeric', 'min:3', 'max:40'],
            'label_template.footer_text' => ['nullable', 'string', 'max:60'],
            'purchase_contract_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'purchase_contract_content' => ['sometimes', 'nullable', 'string', 'max:5000'],
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
            'bank_accounts.*.vat_preferred' => ['sometimes', 'boolean'],
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
        ], $this->validationMessages(), $this->validationAttributes());

        // Quyền được cộng dồn: một người có thể vừa quản lý đơn vị/kho vừa quản lý tài khoản thanh toán.
        $allowedKeys = [];
        if ($user->can('settings.edit')) {
            $allowedKeys = array_merge($allowedKeys, array_diff(array_keys($data), $this->bankSettingKeys));
        }
        if ($user->can('units.manage')) {
            $allowedKeys = array_merge($allowedKeys, $this->inventorySettingKeys);
        }
        if ($this->canUpdateBankSettings($request)) {
            $allowedKeys = array_merge($allowedKeys, $this->bankSettingKeys);
        }
        $data = array_intersect_key($data, array_flip($allowedKeys));

        // The camera list is stored by DoorCameraAccess because it owns
        // credential encryption and the compatibility mirror for old flows.
        $cameraListChanged = array_key_exists('face_cameras', $data);
        $cameraList = $cameraListChanged ? (array) $data['face_cameras'] : [];
        unset($data['face_cameras']);

        // Dán nguyên link kiểu rtsp://admin:matkhau@ip/... thì tách tài khoản/mật khẩu ra lưu riêng (mật khẩu được mã hóa),
        // địa chỉ lưu lại không còn mật khẩu để khỏi lộ khi trả về trình duyệt.
        if (! $cameraListChanged && array_key_exists('face_cctv_snapshot_url', $data) && filled($data['face_cctv_snapshot_url'])) {
            [$cleanUrl, $cameraUser, $cameraPassword] = $this->splitCameraCredentials((string) $data['face_cctv_snapshot_url']);
            $data['face_cctv_snapshot_url'] = $cleanUrl;
            if ($cameraUser !== null) {
                $data['face_cctv_username'] = mb_substr($cameraUser, 0, 100);
                if (filled($cameraPassword)) {
                    $data['face_cctv_password'] = mb_substr($cameraPassword, 0, 200);
                }
            }
        }

        if (! $cameraListChanged && array_key_exists('face_cctv_view_rtsp_url', $data) && filled($data['face_cctv_view_rtsp_url'])) {
            [$viewUrl, $cameraUser, $cameraPassword] = $this->splitCameraCredentials((string) $data['face_cctv_view_rtsp_url']);
            $data['face_cctv_view_rtsp_url'] = $viewUrl;
            // Credentials are shared with the main stream. Never return/store a
            // plaintext password in the browser-visible stream URL.
            if ($cameraUser !== null) {
                $data['face_cctv_username'] = mb_substr($cameraUser, 0, 100);
                if (filled($cameraPassword)) {
                    $data['face_cctv_password'] = mb_substr($cameraPassword, 0, 200);
                }
            }
        }

        $cameraChanged = $cameraListChanged || (bool) array_intersect([
            'face_door_camera_enabled', 'face_cctv_snapshot_url', 'face_cctv_view_rtsp_url',
            'face_cctv_auth', 'face_cctv_username', 'face_cctv_password',
            // These fields can cause the browser to auto-generate a new RTSP
            // source, so include them in the apply path as well.
            'face_camera_brand', 'face_camera_host', 'face_camera_channel',
        ], array_keys($data));

        foreach ($data as $key => $value) {
            if ($key === 'label_template') {
                Setting::set($key, json_encode(\App\Support\LabelTemplate::normalize($value), JSON_UNESCAPED_UNICODE));
            } elseif (in_array($key, ['purchase_contract_title', 'purchase_contract_content'], true) && trim((string) $value) === '') {
                // Để trống nghĩa là dùng lại nội dung hợp đồng mặc định.
                Setting::forget($key);
            } elseif ($key === 'face_cctv_password') {
                // Mật khẩu camera được mã hóa; để trống nghĩa là giữ nguyên mật khẩu đã lưu.
                if (filled($value)) {
                    Setting::set($key, Crypt::encryptString((string) $value));
                }
            } elseif ($key !== 'bank_accounts') {
                Setting::set($key, $value);
            }
        }

        if ($cameraListChanged) {
            DoorCameraAccess::storeCameras($cameraList);
        }

        // Xóa địa chỉ camera thì xóa luôn tài khoản/mật khẩu đã lưu.
        if (! $cameraListChanged && array_key_exists('face_cctv_snapshot_url', $data) && blank($data['face_cctv_snapshot_url'])) {
            Setting::forget('face_cctv_username');
            Setting::forget('face_cctv_password');
        }

        $cameraApplied = ! $cameraChanged || $this->applyDoorCameraConfiguration();

        if (array_key_exists('bank_accounts', $data)) {
            $existingAccounts = collect(json_decode((string) setting('bank_accounts', '[]'), true) ?: [])->keyBy(fn ($account) => (string) ($account['id'] ?? ''));
            $usedIds = [];
            $accounts = collect($data['bank_accounts'])
                ->filter(fn ($account) => filled($account['bank_bin'] ?? null) && filled($account['bank_account'] ?? null))
                ->values()
                ->map(function ($account) use ($existingAccounts, &$usedIds): array {
                    // Id trống hoặc trùng sẽ được cấp mới, tránh việc tài khoản mới thừa hưởng khóa API của tài khoản cũ.
                    $id = trim((string) ($account['id'] ?? ''));
                    if ($id === '' || in_array($id, $usedIds, true)) {
                        $id = 'bank-' . Str::lower(Str::random(10));
                    }
                    $usedIds[] = $id;

                    $existing = $existingAccounts->get($id, []);
                    $provider = in_array($account['payment_provider'] ?? '', ['apipay', 'sepay'], true) ? $account['payment_provider'] : '';
                    // Khóa/secret chỉ được giữ lại khi vẫn dùng đúng nhà cung cấp đã cấu hình.
                    $keepSecrets = $provider !== '' && ($existing['payment_provider'] ?? '') === $provider;
                    $entry = [
                        'id' => $id,
                        'bank_bin' => (string) $account['bank_bin'],
                        'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                        'bank_account' => trim((string) $account['bank_account']),
                        'bank_account_name' => trim((string) ($account['bank_account_name'] ?? '')),
                        'transfer_content_mode' => ($account['transfer_content_mode'] ?? 'default') === 'invoice' ? 'invoice' : 'default',
                        'is_default' => (bool) ($account['is_default'] ?? false),
                        'vat_preferred' => (bool) ($account['vat_preferred'] ?? false),
                        'payment_provider' => $provider,
                        'payment_provider_account_id' => $provider === '' ? '' : trim((string) ($account['payment_provider_account_id'] ?? '')),
                    ];
                    foreach (['payment_api_key', 'payment_api_access_key', 'payment_api_secret_key', 'payment_webhook_secret'] as $secretKey) {
                        $incoming = $provider === '' ? '' : trim((string) ($account[$secretKey] ?? ''));
                        $entry[$secretKey] = $incoming !== ''
                            ? Crypt::encryptString($incoming)
                            : ($keepSecrets ? (string) ($existing[$secretKey] ?? '') : '');
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
                $vatPreferredIndex = $accounts->search(fn ($account) => $account['vat_preferred']);
                $accounts = $accounts->map(fn ($account, $index) => [
                    ...$account,
                    'vat_preferred' => $vatPreferredIndex !== false && $index === $vatPreferredIndex,
                ]);
                $defaultAccount = $accounts[$defaultIndex];
            } else {
                $defaultAccount = null;
            }

            Setting::set('bank_accounts', json_encode($accounts->all(), JSON_UNESCAPED_UNICODE));
            Setting::set('bank_bin', $defaultAccount['bank_bin'] ?? '');
            Setting::set('bank_account', $defaultAccount['bank_account'] ?? '');
            Setting::set('bank_account_name', $defaultAccount['bank_account_name'] ?? '');
        } elseif (array_intersect(['bank_bin', 'bank_account', 'bank_account_name'], array_keys($data))
            && (json_decode((string) setting('bank_accounts', '[]'), true) ?: []) === []) {
            // Chỉ dùng đường cũ (1 tài khoản) khi chưa có danh sách tài khoản, để không ghi đè nhiều tài khoản đã cấu hình.
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

        // `back()` returns a RedirectResponse, not a fluent Collection-like
        // object.  Do not call `when()` here: RedirectResponse has no such
        // method and saving the Camera section would turn a successful write
        // into a 500 error.
        $response = back()->with('success', $cameraChanged && $cameraApplied
            ? 'Đã lưu và áp dụng cấu hình camera mới.'
            : 'Đã lưu cài đặt');

        if ($cameraChanged && ! $cameraApplied) {
            $response->with('error', 'Đã lưu địa chỉ camera, nhưng không thể làm mới Camera Platform. Mở lại phần Camera cửa để xem trạng thái dịch vụ.');
        }

        return $response;
    }

    /** Đồng bộ RTSP đã lưu sang worker và MediaMTX trước khi trả kết quả lưu. */
    private function applyDoorCameraConfiguration(): bool
    {
        if (DoorCameraAccess::configuredCameras() === []) {
            // Nothing is running yet, but saving an incomplete camera form is
            // still valid and must not be reported as a failed application.
            return true;
        }

        try {
            if (Artisan::call('camera-intelligence:configure') !== 0) {
                Log::warning('Không thể tạo lại cấu hình Camera Intelligence sau khi lưu Cài đặt.');

                return false;
            }

            // Nạp ngay worker đang chạy. Header khóa ngăn ứng dụng khác trên LAN
            // yêu cầu service local đổi nguồn camera.
            $reload = Http::timeout(8)->withHeaders(['X-Camera-Key' => DoorCameraAccess::key()])
                ->post(rtrim((string) config('camera_intelligence.service_url'), '/').'/reload');
            if (! $reload->successful()) {
                Log::warning('Camera Intelligence từ chối nạp lại cấu hình RTSP.', ['status' => $reload->status()]);

                return false;
            }

            // MediaMTX keeps RTSP sources in memory. Refresh it synchronously
            // so the success notification means both the worker and WebRTC
            // have moved to the saved address.  The worker was already reloaded
            // above, therefore refresh-windows.ps1 deliberately does not stop
            // its scheduled task a second time.
            if (PHP_OS_FAMILY === 'Windows') {
                $script = base_path('deploy/camera-platform/refresh-windows.ps1');
                if (is_file($script)) {
                    $refresh = new Process([
                        'powershell.exe', '-NoProfile', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', $script,
                    ], base_path(), timeout: 20);
                    $refresh->run();
                    if (! $refresh->isSuccessful()) {
                        Log::warning('MediaMTX không thể nạp lại nguồn RTSP mới.', ['output' => trim($refresh->getErrorOutput())]);

                        return false;
                    }
                }
            }
            return true;
        } catch (\Throwable $exception) {
            Log::warning('Không thể làm mới Camera Platform sau khi đổi RTSP.', ['exception' => $exception->getMessage()]);

            return false;
        }
    }

    /** Danh sách tài khoản ngân hàng đã bỏ khóa bí mật, dùng chung cho trang Cài đặt và dữ liệu chia sẻ toàn app. */
    public static function publicBankAccounts(): array
    {
        return collect(json_decode((string) setting('bank_accounts', '[]'), true) ?: [])->map(function (array $account): array {
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
        })->values()->all();
    }

    private function validationMessages(): array
    {
        return [
            'required' => ':attribute không được để trống.',
            'string' => ':attribute không hợp lệ.',
            'numeric' => ':attribute phải là một số.',
            'integer' => ':attribute phải là số nguyên.',
            'boolean' => ':attribute không hợp lệ.',
            'array' => ':attribute không hợp lệ.',
            'in' => ':attribute có giá trị không hợp lệ.',
            'min.numeric' => ':attribute phải từ :min trở lên.',
            'max.numeric' => ':attribute không được vượt quá :max.',
            'max.string' => ':attribute không được dài quá :max ký tự.',
            'max.array' => ':attribute chỉ được tối đa :max mục.',
            'required_with' => ':attribute là bắt buộc khi đã nhập thông tin tài khoản.',
        ];
    }

    private function validationAttributes(): array
    {
        return [
            'shop_name' => 'Tên cửa hàng',
            'currency_symbol' => 'Ký hiệu tiền',
            'inventory_alert_threshold' => 'Ngưỡng cảnh báo tồn kho',
            'stock_import_vat_rate' => 'Thuế suất VAT nhập hàng',
            'pos_vat_rate' => 'Thuế suất VAT bán hàng',
            'customer_points_amount_per_point' => 'Số tiền cho 1 điểm',
            'customer_points_debt_percent' => 'Tỷ lệ điểm đơn nợ',
            'print_shop_subtitle' => 'Dòng giới thiệu cửa hàng',
            'print_shop_address' => 'Địa chỉ cửa hàng',
            'print_shop_phone' => 'Số điện thoại cửa hàng',
            'purchase_contract_title' => 'Tiêu đề hợp đồng',
            'purchase_contract_content' => 'Nội dung hợp đồng',
            'bank_transfer_content' => 'Nội dung chuyển khoản',
            'bank_accounts' => 'Danh sách tài khoản',
            'bank_accounts.*.bank_bin' => 'Ngân hàng',
            'bank_accounts.*.bank_account' => 'Số tài khoản',
            'bank_accounts.*.bank_account_name' => 'Tên chủ tài khoản',
            'label_template.width_mm' => 'Chiều rộng tem',
            'label_template.height_mm' => 'Chiều cao tem',
            'label_template.gap_mm' => 'Khoảng cách tem',
            'label_template.margin_mm' => 'Lề tem',
            'label_template.name_size' => 'Cỡ chữ tên',
            'label_template.code_size' => 'Cỡ chữ mã',
            'label_template.price_size' => 'Cỡ chữ giá',
            'label_template.barcode_height_mm' => 'Chiều cao mã vạch',
            'label_template.footer_text' => 'Dòng chân tem',
        ];
    }

    /**
     * Địa chỉ camera hợp lệ: ảnh chụp http/https (POS, đăng ký khuôn mặt) hoặc luồng rtsp/rtsps (worker camera cửa).
     *
     * @return array<string, mixed>|null
     */
    public static function parseCameraUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || preg_match('/\s/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https', 'rtsp', 'rtsps'], true) || blank($parts['host'] ?? null)) {
            return null;
        }

        return $parts;
    }

    /**
     * Tách tài khoản/mật khẩu nằm trong địa chỉ camera.
     *
     * @return array{0: string, 1: ?string, 2: ?string} [địa chỉ không kèm mật khẩu, tài khoản, mật khẩu]
     */
    private function splitCameraCredentials(string $url): array
    {
        $url = trim($url);
        $parts = self::parseCameraUrl($url);
        if ($parts === null || ! isset($parts['user'])) {
            return [$url, null, null];
        }

        $clean = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        return [
            $clean,
            rawurldecode((string) $parts['user']),
            isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null,
        ];
    }

    private function canUpdateBankSettings(Request $request): bool
    {
        $user = $request->user();

        return $user?->can('payment_accounts.manage') ?? false;
    }

    public function vietQrBanks(): array
    {
        $freshCacheKey = 'settings.vietqr_banks';
        $staleCacheKey = 'settings.vietqr_banks.stale';
        $failedCacheKey = 'settings.vietqr_banks.failed';
        $banks = Cache::get($freshCacheKey);

        if (is_array($banks)) {
            return $banks;
        }

        // Máy không có Internet hoặc VietQR đang lỗi: không gọi lại mỗi lần mở trang (mỗi lần chờ vài giây).
        if (Cache::has($failedCacheKey)) {
            return Cache::get($staleCacheKey, []);
        }

        try {
            $response = Http::connectTimeout(2)->timeout(4)->get('https://api.vietqr.io/v2/banks');
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
            Cache::put($failedCacheKey, true, now()->addMinutes(5));

            return Cache::get($staleCacheKey, []);
        }
    }

    public function vietQrApps(string $platform): array
    {
        abort_unless(in_array($platform, ['android', 'ios'], true), 404);

        return Cache::remember("settings.vietqr_apps.{$platform}", now()->addDay(), function () use ($platform): array {
            try {
                $response = Http::timeout(5)->get("https://api.vietqr.io/v2/{$platform}-app-deeplinks");
                $apps = $response->successful() ? $response->json('apps') : null;
                if (! is_array($apps)) return [];

                return collect($apps)
                    ->filter(fn ($app) => is_array($app) && filled($app['appId'] ?? null))
                    ->map(fn ($app) => [
                        'app_id' => (string) $app['appId'],
                        'app_name' => (string) ($app['appName'] ?? $app['appId']),
                        'bank_name' => (string) ($app['bankName'] ?? ''),
                        'app_logo' => (string) ($app['appLogo'] ?? ''),
                    ])
                    ->values()
                    ->all();
            } catch (\Throwable $exception) {
                report($exception);

                return [];
            }
        });
    }

}
