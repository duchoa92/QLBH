<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Api\CameraIntelligenceController;
use App\Support\DoorCameraAccess;
use Illuminate\Http\JsonResponse;

/** Phần "Camera cửa" trong Cài đặt: khóa tự sinh, lệnh cài worker và trạng thái worker. */
class DoorCameraSettingsController extends Controller
{
    /** Thông tin hiện trong Cài đặt (tự tạo khóa nếu chưa có). */
    public static function info(): array
    {
        return CameraIntelligenceController::status() + [
            'key' => DoorCameraAccess::ensureKey(),
            'api_url' => app()->environment('local') ? 'http://127.0.0.1/api' : rtrim(url('/api'), '/'),
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
        return response()->json(['key' => DoorCameraAccess::regenerate()]);
    }
}
