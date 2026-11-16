<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\DoorCameraAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chỉ cho worker camera cửa (chương trình chạy nền, không có phiên đăng nhập) gọi API,
 * bằng khóa bí mật gửi trong header X-Camera-Key (khóa tự sinh ở Cài đặt).
 */
class DoorCameraKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DoorCameraAccess::hasKey()) {
            return response()->json(['message' => 'Chưa có khóa camera: mở Cài đặt → Khách hàng → Camera cửa để hệ thống tạo khóa.'], 503);
        }

        if (! DoorCameraAccess::matches((string) $request->header('X-Camera-Key', ''))) {
            return response()->json(['message' => 'Khóa camera không hợp lệ (lấy lệnh cài mới ở Cài đặt → Khách hàng → Camera cửa).'], 401);
        }

        return $next($request);
    }
}
