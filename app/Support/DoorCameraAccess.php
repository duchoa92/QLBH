<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Khóa truy cập của worker camera cửa và thông tin camera lấy từ Cài đặt.
 * Khóa tự sinh khi cần và được lưu mã hóa; worker chỉ cần biết địa chỉ máy chủ + khóa này.
 */
final class DoorCameraAccess
{
    public const SETTING_KEY = 'door_camera_key';

    /** Khóa đang lưu trong Cài đặt ('' nếu chưa có hoặc không giải mã được). */
    public static function key(): string
    {
        $stored = setting(self::SETTING_KEY);
        if (blank($stored)) {
            return '';
        }

        try {
            return Crypt::decryptString((string) $stored);
        } catch (\Throwable) {
            return '';
        }
    }

    /** Trả về khóa hiện có, chưa có thì tự sinh. */
    public static function ensureKey(): string
    {
        $key = self::key();

        return $key !== '' ? $key : self::regenerate();
    }

    /** Sinh khóa mới (khóa cũ hết hiệu lực, worker phải cập nhật khóa mới). */
    public static function regenerate(): string
    {
        $key = Str::random(48);
        Setting::set(self::SETTING_KEY, Crypt::encryptString($key));

        return $key;
    }

    /** Có khóa nào (tự sinh hoặc đặt trong .env) để worker xác thực chưa. */
    public static function hasKey(): bool
    {
        return self::key() !== '' || (string) config('door_camera.key', '') !== '';
    }

    public static function matches(string $provided): bool
    {
        if ($provided === '') {
            return false;
        }

        foreach ([self::key(), (string) config('door_camera.key', '')] as $candidate) {
            if ($candidate !== '' && hash_equals($candidate, $provided)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{snapshot_url: string, auth: string, username: string, password: string} */
    public static function camera(): array
    {
        $password = '';
        if (filled($encrypted = setting('face_cctv_password'))) {
            try {
                $password = Crypt::decryptString((string) $encrypted);
            } catch (\Throwable) {
                $password = '';
            }
        }

        $auth = (string) setting('face_cctv_auth', 'none');

        return [
            'snapshot_url' => trim((string) setting('face_cctv_snapshot_url', '')),
            'auth' => in_array($auth, ['none', 'basic', 'digest'], true) ? $auth : 'none',
            'username' => (string) setting('face_cctv_username', ''),
            'password' => $password,
        ];
    }

    /** Dấu vân tay của cấu hình camera: đổi địa chỉ/tài khoản trong Cài đặt thì worker tự nạp lại. */
    public static function configHash(): string
    {
        return substr(hash('sha256', (string) json_encode(self::camera())), 0, 16);
    }
}
