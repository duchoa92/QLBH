<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Api\CameraIntelligenceController;
use App\Support\DoorCameraAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/** Phần "Camera cửa" trong Cài đặt: khóa tự sinh, lệnh cài worker và trạng thái worker. */
class DoorCameraSettingsController extends Controller
{
    /** Thông tin hiện trong Cài đặt (tự tạo khóa nếu chưa có). */
    public static function info(): array
    {
        return CameraIntelligenceController::status() + [
            'key' => DoorCameraAccess::ensureKey(),
            'api_url' => rtrim(url('/api'), '/'),
            'camera_configured' => filled(setting('face_cctv_snapshot_url')),
            'media_server' => ['ready' => false, 'message' => 'MediaMTX sẽ báo sẵn sàng sau khi cài camera platform.'],
            'server_os' => PHP_OS_FAMILY,
        ];
    }

    /** Trang Cài đặt hỏi mỗi vài giây để hiện worker đang chạy hay chưa. */
    public function status(): JsonResponse
    {
        return response()->json(CameraIntelligenceController::status() + [
            'camera_configured' => filled(setting('face_cctv_snapshot_url')),
            'media_server' => ['ready' => false, 'message' => 'MediaMTX sẽ báo sẵn sàng sau khi cài camera platform.'],
            'server_os' => PHP_OS_FAMILY,
        ]);
    }

    /** Tạo khóa mới: khóa cũ hết hiệu lực, worker phải chạy lại lệnh cài với khóa mới. */
    public function regenerateKey(): JsonResponse
    {
        $oldKey = DoorCameraAccess::key();
        DoorCameraAccess::regenerate();

        try {
            if (Artisan::call('camera-intelligence:configure') === 0) {
                // The running worker still accepts the former key; use it once
                // to authorize loading the newly generated key from config.yaml.
                Http::timeout(3)->withHeaders(['X-Camera-Key' => $oldKey])
                    ->post(rtrim((string) config('camera_intelligence.service_url'), '/').'/reload');
                $script = base_path('deploy/camera-platform/refresh-windows.ps1');
                if (PHP_OS_FAMILY === 'Windows' && is_file($script)) {
                    $refresh = new Process([
                        'powershell.exe', '-NoProfile', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', $script,
                    ], base_path(), timeout: 20);
                    $refresh->run();
                    if (! $refresh->isSuccessful()) {
                        Log::warning('Không thể làm mới MediaMTX sau khi tạo khóa camera mới.', ['output' => trim($refresh->getErrorOutput())]);
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Không thể làm mới Camera Platform sau khi tạo khóa mới.', ['exception' => $exception->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }
}
