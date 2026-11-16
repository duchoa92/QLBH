<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DoorCameraAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Nhịp báo "còn sống" của worker camera cửa; trả về cho worker biết có nên xử lý khung hình hay không. */
class DoorCameraController extends Controller
{
    public const CACHE_KEY = 'door_camera.heartbeat';

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'camera_ok' => ['nullable', 'boolean'],
            'faces' => ['nullable', 'integer', 'min:0', 'max:100'],
            'message' => ['nullable', 'string', 'max:200'],
        ]);

        Cache::put(self::CACHE_KEY, [
            'at' => now()->timestamp,
            'camera_ok' => (bool) ($data['camera_ok'] ?? true),
            'faces' => (int) ($data['faces'] ?? 0),
            'message' => $data['message'] ?? null,
        ], 300);

        return response()->json([
            'ok' => true,
            'enabled' => self::enabled(),
            'config_hash' => DoorCameraAccess::configHash(),
        ]);
    }

    /** Thông tin camera nhập ở Cài đặt: worker đọc về, không phải khai báo lại trong file .env của worker. */
    public function config(): JsonResponse
    {
        return response()->json([
            'enabled' => self::enabled(),
            'camera' => DoorCameraAccess::camera(),
            'config_hash' => DoorCameraAccess::configHash(),
        ]);
    }

    /** Cần bật cả nhận diện khuôn mặt lẫn ghi nhận lượt khách trong Cài đặt. */
    public static function enabled(): bool
    {
        return filter_var(setting('face_recognition_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(setting('face_visit_tracking_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{online: bool, camera_ok: bool, message: ?string, seconds_ago: ?int} */
    public static function status(): array
    {
        $beat = Cache::get(self::CACHE_KEY);
        $online = is_array($beat) && ($beat['at'] ?? 0) >= now()->timestamp - (int) config('door_camera.online_seconds', 30);

        return [
            'online' => $online,
            'camera_ok' => $online && ($beat['camera_ok'] ?? true),
            'message' => $online ? ($beat['message'] ?? null) : null,
            'seconds_ago' => is_array($beat) ? max(0, now()->timestamp - (int) ($beat['at'] ?? 0)) : null,
        ];
    }
}
