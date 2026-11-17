<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DoorCameraAccess;
use App\Support\FaceTuning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Nhịp báo "còn sống" của worker camera cửa; trả về cho worker biết có nên xử lý khung hình hay không. */
class DoorCameraController extends Controller
{
    public const CACHE_KEY = 'door_camera.heartbeat';

    public const FRAME_KEY = 'door_camera.frame';

    private const WANTED_KEY = 'door_camera.frame_wanted';

    public const FACES_KEY = 'door_camera.faces';

    private const FACES_FRESH_SECONDS = 3;

    public const SNAPSHOT_KEY = 'door_camera.snapshot';

    private const SNAPSHOT_WANTED_KEY = 'door_camera.snapshot_wanted';

    private const SNAPSHOT_MAX_BYTES = 3 * 1024 * 1024;

    private const FRAME_MAX_BYTES = 400 * 1024;

    private const FRAME_FRESH_SECONDS = 10;

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
            // Có người đang xem camera cửa (POS / trang Lượt khách): worker nên gửi khung hình qua POST /door-camera/frame.
            'send_frame' => Cache::has(self::WANTED_KEY),
            // Có người cần ảnh gốc độ phân giải cao (đăng ký khuôn mặt từ camera cửa): worker gửi ảnh nguyên bản qua POST /door-camera/snapshot.
            'send_snapshot' => Cache::has(self::SNAPSHOT_WANTED_KEY),
        ]);
    }

    /** Worker gửi 1 khung hình nhỏ (JPEG) để POS và trang Lượt khách xem camera cửa; chỉ giữ ảnh mới nhất trong bộ nhớ đệm vài giây. */
    public function storeFrame(Request $request): JsonResponse
    {
        $body = (string) $request->getContent();
        if ($body === '' || strlen($body) > self::FRAME_MAX_BYTES || ! str_starts_with($body, "\xFF\xD8\xFF")) {
            return response()->json(['message' => 'Khung hình phải là ảnh JPEG nhỏ hơn 400KB.'], 422);
        }

        Cache::put(self::FRAME_KEY, ['at' => now()->timestamp, 'jpeg' => base64_encode($body)], 60);

        return response()->json(['ok' => true]);
    }

    /**
     * Worker gửi các khuôn mặt đang thấy (toạ độ 0..1 theo khung hình) cùng tên khách nếu đã nhận ra.
     * Giao diện vẽ khung bắt mặt + tên lên ảnh xem trước; dữ liệu chỉ giữ vài giây trong bộ nhớ đệm.
     */
    public function storeFaces(Request $request): JsonResponse
    {
        $data = $request->validate([
            'faces' => ['present', 'array', 'max:12'],
            'faces.*.id' => ['required', 'integer'],
            'faces.*.x' => ['required', 'numeric', 'between:-0.5,1.5'],
            'faces.*.y' => ['required', 'numeric', 'between:-0.5,1.5'],
            'faces.*.w' => ['required', 'numeric', 'between:0,2'],
            'faces.*.h' => ['required', 'numeric', 'between:0,2'],
            'faces.*.kind' => ['required', 'in:known,unknown,scanning'],
            'faces.*.label' => ['nullable', 'string', 'max:80'],
        ]);

        Cache::put(self::FACES_KEY, ['at' => now()->timestamp, 'faces' => array_values($data['faces'])], 30);

        return response()->json(['ok' => true]);
    }

    /** Khuôn mặt mới nhất worker vừa gửi (rỗng nếu đã cũ), đính kèm vào ảnh xem trước dạng base64 JSON ở header. */
    private function facesHeader(): array
    {
        $stored = Cache::get(self::FACES_KEY);
        $faces = is_array($stored) && ($stored['at'] ?? 0) >= now()->timestamp - self::FACES_FRESH_SECONDS ? ($stored['faces'] ?? []) : [];

        return ['X-Door-Faces' => base64_encode(json_encode($faces, JSON_UNESCAPED_UNICODE))];
    }

    /** Worker gửi ảnh nguyên bản từ camera (JPEG, độ phân giải gốc) khi có người cần chụp ảnh đăng ký khuôn mặt. */
    public function storeSnapshot(Request $request): JsonResponse
    {
        $body = (string) $request->getContent();
        if ($body === '' || strlen($body) > self::SNAPSHOT_MAX_BYTES || ! str_starts_with($body, "\xFF\xD8\xFF")) {
            return response()->json(['message' => 'Ảnh phải là JPEG nhỏ hơn 3MB.'], 422);
        }

        Cache::put(self::SNAPSHOT_KEY, ['at' => microtime(true), 'jpeg' => base64_encode($body)], 60);

        return response()->json(['ok' => true]);
    }

    /**
     * Lấy 1 ảnh gốc mới từ camera cửa (camera RTSP không đọc trực tiếp được từ máy chủ web): báo worker gửi ảnh rồi đợi tối đa vài giây.
     * Trả về null nếu worker không gửi kịp.
     */
    public static function freshSnapshot(int $waitSeconds = 12): ?string
    {
        $startedAt = microtime(true);
        Cache::put(self::SNAPSHOT_WANTED_KEY, 1, 20);

        do {
            $stored = Cache::get(self::SNAPSHOT_KEY);
            if (is_array($stored) && ($stored['at'] ?? 0) >= $startedAt - 0.5) {
                $jpeg = base64_decode((string) ($stored['jpeg'] ?? ''), true);
                if ($jpeg !== false && $jpeg !== '') {
                    return $jpeg;
                }
            }
            usleep(250_000);
        } while (microtime(true) - $startedAt < $waitSeconds);

        return null;
    }

    /**
     * Ảnh xem trước camera cửa cho giao diện. Ưu tiên khung hình worker vừa gửi (dùng được cho camera RTSP);
     * chưa có thì lấy ảnh chụp http của camera (cache 1 giây để nhiều quầy cùng xem không dồn tải lên camera).
     */
    public function frame(): Response
    {
        if (! filter_var(setting('face_recognition_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return response('', 204);
        }

        Cache::put(self::WANTED_KEY, 1, 15);

        $headers = ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store', 'Access-Control-Expose-Headers' => 'X-Door-Faces'] + $this->facesHeader();

        $stored = Cache::get(self::FRAME_KEY);
        if (is_array($stored) && ($stored['at'] ?? 0) >= now()->timestamp - self::FRAME_FRESH_SECONDS) {
            $jpeg = base64_decode((string) ($stored['jpeg'] ?? ''), true);
            if ($jpeg !== false && $jpeg !== '') {
                return response($jpeg, 200, $headers);
            }
        }

        $camera = DoorCameraAccess::camera();
        if (! preg_match('#^https?://#i', $camera['snapshot_url'])) {
            return response('', 204);
        }

        $jpeg = Cache::remember('door_camera.http_frame', 1, function () use ($camera): string {
            try {
                $request = Http::timeout(4)->withoutRedirecting()->accept('image/*');
                if ($camera['username'] !== '' && $camera['auth'] === 'digest') {
                    $request = $request->withDigestAuth($camera['username'], $camera['password']);
                } elseif ($camera['username'] !== '' && $camera['auth'] === 'basic') {
                    $request = $request->withBasicAuth($camera['username'], $camera['password']);
                }
                $response = $request->get($camera['snapshot_url']);
            } catch (\Throwable) {
                return '';
            }

            $isImage = str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/');

            return $response->successful() && $isImage && strlen($response->body()) <= 8 * 1024 * 1024 ? base64_encode($response->body()) : '';
        });

        if ($jpeg === '') {
            return response('', 204);
        }

        return response(base64_decode($jpeg), 200, $headers);
    }

    /** Thông tin camera nhập ở Cài đặt: worker đọc về, không phải khai báo lại trong file .env của worker. */
    public function config(): JsonResponse
    {
        return response()->json([
            'enabled' => self::enabled(),
            'camera' => DoorCameraAccess::camera(),
            'tuning' => FaceTuning::worker(),
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
