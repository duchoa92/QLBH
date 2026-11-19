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
    public const CAMERAS_SETTING_KEY = 'face_cameras';
    public const DEFAULT_CAMERA_ID = 'door-1';

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

    /**
     * @return array<int, array{id: string, name: string, enabled: bool, snapshot_url: string, view_rtsp_url: string, auth: string, username: string, password: string, brand: string, host: string, channel: int}>
     */
    public static function cameras(): array
    {
        $stored = self::storedCameras();
        if ($stored === null) {
            return [self::legacyCamera()];
        }

        $cameras = [];
        foreach ($stored as $entry) {
            if (! is_array($entry) || ! self::validId((string) ($entry['id'] ?? ''))) {
                continue;
            }
            $id = (string) $entry['id'];
            if (isset($cameras[$id])) {
                continue;
            }
            $cameras[$id] = self::decodeCamera($entry);
        }

        return array_values($cameras);
    }

    /**
     * Return a camera by id, falling back to the default/first configured entry.
     * This retains the old one-camera API for POS and enrollment callers.
     *
     * @return array{id: string, name: string, enabled: bool, snapshot_url: string, view_rtsp_url: string, auth: string, username: string, password: string, brand: string, host: string, channel: int}
     */
    public static function camera(string $id = self::DEFAULT_CAMERA_ID): array
    {
        $cameras = self::cameras();
        foreach ($cameras as $camera) {
            if ($camera['id'] === $id) {
                return $camera;
            }
        }

        return $cameras[0] ?? self::emptyCamera();
    }

    /** Cameras complete enough to be emitted to Camera Intelligence/MediaMTX. */
    public static function configuredCameras(): array
    {
        return array_values(array_filter(self::cameras(), static fn (array $camera): bool => $camera['enabled']
            && preg_match('#^rtsps?://#i', $camera['snapshot_url']) === 1
            && preg_match('#^rtsps?://#i', $camera['view_rtsp_url']) === 1));
    }

    /** Cameras usable for one-off enrollment snapshots, including old HTTP JPEG endpoints. */
    public static function snapshotCameras(): array
    {
        return array_values(array_filter(self::cameras(), static fn (array $camera): bool => $camera['enabled']
            && preg_match('#^(?:https?|rtsps?)://#i', $camera['snapshot_url']) === 1));
    }

    /** Browser-safe configuration. Passwords are omitted; Settings may show credential-free RTSP URLs to its authorized editor. */
    public static function publicCameras(): array
    {
        return array_map(static fn (array $camera): array => [
            'id' => $camera['id'],
            'name' => $camera['name'],
            'enabled' => $camera['enabled'],
            'configured' => preg_match('#^rtsps?://#i', $camera['snapshot_url']) === 1
                && preg_match('#^rtsps?://#i', $camera['view_rtsp_url']) === 1,
            'snapshot_configured' => preg_match('#^(?:https?|rtsps?)://#i', $camera['snapshot_url']) === 1,
            'brand' => $camera['brand'],
            'host' => $camera['host'],
            'channel' => $camera['channel'],
            'snapshot_url' => $camera['snapshot_url'],
            'view_rtsp_url' => $camera['view_rtsp_url'],
            'auth' => $camera['auth'],
            'username' => $camera['username'],
            'has_password' => $camera['password'] !== '',
        ], self::cameras());
    }

    /**
     * Store the camera list. A blank password preserves that camera's old
     * encrypted password, so opening Settings never exposes credentials.
     *
     * @param array<int, array<string, mixed>> $incoming
     */
    public static function storeCameras(array $incoming): void
    {
        $existing = [];
        $existingRows = self::storedCameras();
        // First save after the multi-camera upgrade must retain the old
        // encrypted door-camera password even though the browser submits an
        // intentionally blank password field.
        if ($existingRows === null) {
            $existingRows = [[
                'id' => self::DEFAULT_CAMERA_ID,
                'password' => (string) setting('face_cctv_password', ''),
            ]];
        }
        foreach ($existingRows as $camera) {
            if (is_array($camera) && self::validId((string) ($camera['id'] ?? ''))) {
                $existing[(string) $camera['id']] = $camera;
            }
        }

        $stored = [];
        foreach ($incoming as $camera) {
            $id = strtolower(trim((string) ($camera['id'] ?? '')));
            if (! self::validId($id)) {
                continue;
            }
            $previous = $existing[$id] ?? [];
            [$snapshotUrl, $snapshotUser, $snapshotPassword] = self::splitCredentials((string) ($camera['snapshot_url'] ?? ''));
            [$viewUrl, $viewUser, $viewPassword] = self::splitCredentials((string) ($camera['view_rtsp_url'] ?? ''));
            $username = trim((string) ($camera['username'] ?? ''));
            $password = trim((string) ($camera['password'] ?? ''));
            if ($snapshotUser !== null) {
                $username = $snapshotUser;
            }
            if ($snapshotPassword !== null) {
                $password = $snapshotPassword;
            }
            if ($viewUser !== null) {
                $username = $viewUser;
            }
            if ($viewPassword !== null) {
                $password = $viewPassword;
            }

            $stored[] = [
                'id' => $id,
                'name' => trim((string) ($camera['name'] ?? '')) ?: 'Camera',
                'enabled' => self::toBool($camera['enabled'] ?? true),
                'brand' => self::brand($camera['brand'] ?? 'custom'),
                'host' => trim((string) ($camera['host'] ?? '')),
                'channel' => max(1, min(64, (int) ($camera['channel'] ?? 1))),
                'snapshot_url' => $snapshotUrl,
                'view_rtsp_url' => $viewUrl,
                'auth' => self::auth($camera['auth'] ?? 'none'),
                'username' => mb_substr($username, 0, 100),
                'password' => $password !== ''
                    ? Crypt::encryptString(mb_substr($password, 0, 200))
                    : (string) ($previous['password'] ?? ''),
            ];
        }

        Setting::set(self::CAMERAS_SETTING_KEY, json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Mirror the primary camera in legacy keys for older POS/camera flows.
        $primary = null;
        foreach ($stored as $camera) {
            if ($camera['id'] === self::DEFAULT_CAMERA_ID) {
                $primary = $camera;
                break;
            }
        }
        $primary ??= $stored[0] ?? null;
        if ($primary === null) {
            foreach (['face_cctv_snapshot_url', 'face_cctv_view_rtsp_url', 'face_cctv_username', 'face_cctv_password'] as $key) {
                Setting::forget($key);
            }

            return;
        }
        Setting::set('face_camera_brand', $primary['brand']);
        Setting::set('face_camera_host', $primary['host']);
        Setting::set('face_camera_channel', $primary['channel']);
        Setting::set('face_cctv_snapshot_url', $primary['snapshot_url']);
        Setting::set('face_cctv_view_rtsp_url', $primary['view_rtsp_url']);
        Setting::set('face_cctv_auth', $primary['auth']);
        Setting::set('face_cctv_username', $primary['username']);
        if ($primary['password'] !== '') {
            Setting::set('face_cctv_password', $primary['password']);
        } else {
            Setting::forget('face_cctv_password');
        }
    }

    /** @return array<int, array<string, mixed>>|null */
    private static function storedCameras(): ?array
    {
        $value = setting(self::CAMERAS_SETTING_KEY, null);
        if ($value === null || $value === '') {
            return null;
        }
        $cameras = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($cameras) ? $cameras : null;
    }

    /** @return array{id: string, name: string, enabled: bool, snapshot_url: string, view_rtsp_url: string, auth: string, username: string, password: string, brand: string, host: string, channel: int} */
    private static function legacyCamera(): array
    {
        return [
            'id' => self::DEFAULT_CAMERA_ID,
            'name' => 'Camera cửa',
            'enabled' => true,
            'snapshot_url' => trim((string) setting('face_cctv_snapshot_url', '')),
            'view_rtsp_url' => trim((string) setting('face_cctv_view_rtsp_url', '')),
            'auth' => self::auth(setting('face_cctv_auth', 'none')),
            'username' => (string) setting('face_cctv_username', ''),
            'password' => self::decrypt((string) setting('face_cctv_password', '')),
            'brand' => self::brand(setting('face_camera_brand', 'custom')),
            'host' => (string) setting('face_camera_host', ''),
            'channel' => max(1, min(64, (int) setting('face_camera_channel', 1))),
        ];
    }

    /** @param array<string, mixed> $entry */
    private static function decodeCamera(array $entry): array
    {
        return [
            'id' => (string) $entry['id'],
            'name' => trim((string) ($entry['name'] ?? '')) ?: 'Camera',
            'enabled' => self::toBool($entry['enabled'] ?? true),
            'snapshot_url' => trim((string) ($entry['snapshot_url'] ?? '')),
            'view_rtsp_url' => trim((string) ($entry['view_rtsp_url'] ?? '')),
            'auth' => self::auth($entry['auth'] ?? 'none'),
            'username' => (string) ($entry['username'] ?? ''),
            'password' => self::decrypt((string) ($entry['password'] ?? '')),
            'brand' => self::brand($entry['brand'] ?? 'custom'),
            'host' => (string) ($entry['host'] ?? ''),
            'channel' => max(1, min(64, (int) ($entry['channel'] ?? 1))),
        ];
    }

    /** @return array{id: string, name: string, enabled: bool, snapshot_url: string, view_rtsp_url: string, auth: string, username: string, password: string, brand: string, host: string, channel: int} */
    private static function emptyCamera(): array
    {
        return ['id' => self::DEFAULT_CAMERA_ID, 'name' => 'Camera cửa', 'enabled' => true, 'snapshot_url' => '', 'view_rtsp_url' => '', 'auth' => 'none', 'username' => '', 'password' => '', 'brand' => 'custom', 'host' => '', 'channel' => 1];
    }

    private static function decrypt(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function validId(string $id): bool
    {
        return preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $id) === 1;
    }

    private static function toBool(mixed $value): bool
    {
        return ! in_array($value, [false, 0, '0', 'false', 'off'], true);
    }

    private static function auth(mixed $auth): string
    {
        return in_array($auth, ['none', 'basic', 'digest'], true) ? (string) $auth : 'none';
    }

    private static function brand(mixed $brand): string
    {
        return in_array($brand, ['hikvision', 'dahua', 'imou', 'ezviz', 'reolink', 'uniview', 'vigi', 'custom'], true) ? (string) $brand : 'custom';
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private static function splitCredentials(string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['user'])) {
            return [$url, null, null];
        }
        $clean = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');

        return [$clean, rawurldecode((string) $parts['user']), isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null];
    }

}
