<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DoorCameraAccess;
use App\Support\FaceTuning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Nhịp báo "còn sống" của worker camera cửa; trả về cho worker biết có nên xử lý khung hình hay không. */
class DoorCameraController extends Controller
{
    public const CACHE_KEY = 'door_camera.heartbeat';

    private const FACES_FRESH_SECONDS = 3;

    public const SNAPSHOT_KEY = 'door_camera.snapshot';

    private const SNAPSHOT_WANTED_KEY = 'door_camera.snapshot_wanted';

    private const SNAPSHOT_MAX_BYTES = 3 * 1024 * 1024;

    private const LIVE_DIRECTORY = 'app/private/door-camera-live';

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
            // Worker chỉ gửi dữ liệu nhãn khi trình duyệt đang phát luồng go2rtc.
            'send_faces' => self::facesWanted(),
            // Có người cần ảnh gốc độ phân giải cao (đăng ký khuôn mặt từ camera cửa): worker gửi ảnh nguyên bản qua POST /door-camera/snapshot.
            'send_snapshot' => Cache::has(self::SNAPSHOT_WANTED_KEY),
        ]);
    }

    /**
     * Worker gửi tọa độ và nhãn; giao diện vẽ lên luồng WebRTC liên tục.
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

        $faces = json_encode([
            'at' => (int) floor(microtime(true) * 1000),
            'faces' => array_values($data['faces']),
        ], JSON_UNESCAPED_UNICODE);
        if ($faces === false || ! self::writeLiveData('faces.json', $faces)) {
            return response()->json(['message' => 'Không lưu được dữ liệu nhận diện. Kiểm tra quyền ghi thư mục storage.'], 500);
        }

        return response()->json(['ok' => true]);
    }

    /** Lấy thông tin kết nối WebRTC và đánh dấu đang có giao diện xem camera. */
    public function live(): JsonResponse
    {
        self::markFacesWanted();
        $camera = DoorCameraAccess::camera();
        $isRtsp = preg_match('#^rtsps?://#i', $camera['snapshot_url']) === 1;
        $ready = $isRtsp && $this->syncGo2rtcSource(false);

        return response()->json([
            'ready' => $ready,
            'stream' => (string) config('door_camera.go2rtc_stream_name', 'qlbh_door_camera'),
            'message' => $isRtsp ? ($ready ? null : 'Không kết nối được go2rtc. Kiểm tra go2rtc đang chạy và cấu hình API.') : 'Xem luồng trực tiếp cần camera RTSP.',
        ]);
    }

    /** Worker giữ nhãn ngắn hạn; trang POS/Thống kê đọc metadata tách khỏi luồng video. */
    public function liveFaces(): JsonResponse
    {
        self::markFacesWanted();
        $contents = self::readLiveData('faces.json');
        $stored = $contents !== null ? json_decode($contents, true) : null;
        $faces = is_array($stored) && ($stored['at'] ?? 0) >= (int) floor((microtime(true) - self::FACES_FRESH_SECONDS) * 1000)
            ? ($stored['faces'] ?? [])
            : [];

        return response()->json(['at' => (int) ($stored['at'] ?? 0), 'faces' => $faces]);
    }

    /** Laravel authenticates the viewer; only this server-side call reaches go2rtc's private API. */
    public function webrtcOffer(Request $request): Response|JsonResponse
    {
        $offer = (string) $request->getContent();
        if (strlen($offer) > 256 * 1024 || ! str_starts_with(ltrim($offer), 'v=0')) {
            return response()->json(['message' => 'Đề nghị WebRTC không hợp lệ.'], 422);
        }

        try {
            $stream = (string) config('door_camera.go2rtc_stream_name', 'qlbh_door_camera');
            $camera = DoorCameraAccess::camera();
            $sourceHash = hash('sha256', $stream.'|'.self::rtspSource($camera));
            $transcodeKey = 'door_camera.go2rtc.webrtc_transcode.'.$sourceHash;
            $transcodedStream = (string) config('door_camera.go2rtc_webrtc_stream_name', $stream.'_webrtc');
            $transcodeAttempted = Cache::get($transcodeKey, false) === true;

            if ($transcodeAttempted) {
                if (! $this->syncGo2rtcNamedStream($transcodedStream, 'ffmpeg:'.$stream.'#video=h264', false)) {
                    return response()->json(['message' => 'Không đăng ký được luồng H.264 dự phòng trong go2rtc. Kiểm tra nhật ký dịch vụ.'], 502);
                }
                $response = $this->requestWebrtcOffer($transcodedStream, $offer);
            } else {
                $response = $this->requestWebrtcOffer($stream, $offer);
                if (! $response->successful() && str_contains(strtolower($response->body()), 'codecs not matched')) {
                    $transcodeAttempted = true;
                    Cache::put($transcodeKey, true, 86400);
                    if ($this->syncGo2rtcNamedStream($transcodedStream, 'ffmpeg:'.$stream.'#video=h264', false)) {
                        $response = $this->requestWebrtcOffer($transcodedStream, $offer);
                    }
                }
            }
        } catch (\Throwable $error) {
            report($error);

            return response()->json(['message' => 'Không gọi được go2rtc. Kiểm tra dịch vụ và cấu hình GO2RTC_API_URL.'], 502);
        }

        if (! $response->successful()) {
            $details = strtolower($response->body());
            if ($transcodeAttempted) {
                $message = 'Không phát được video H.265 sau khi thử chuyển mã. Cài FFmpeg trên máy chủ, thêm ffmpeg vào PATH rồi khởi động lại go2rtc; kiểm tra nhật ký nếu lỗi vẫn còn.';
            } elseif (str_contains($details, 'codecs not matched')) {
                $message = 'Camera đang phát codec trình duyệt không hỗ trợ. Cài FFmpeg trên máy chủ, thêm ffmpeg vào PATH rồi khởi động lại go2rtc để bật chuyển mã dự phòng.';
            } else {
                $message = 'go2rtc không tạo được phiên WebRTC. Kiểm tra nguồn RTSP, nhật ký go2rtc và kết nối cổng WebRTC.';
            }

            return response()->json(['message' => $message], 502);
        }

        return response($response->body(), $response->status(), [
            'Content-Type' => $response->header('Content-Type', 'application/sdp'),
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Register the current RTSP URL dynamically; camera credentials never go to the browser. */
    private function syncGo2rtcSource(bool $force): bool
    {
        $camera = DoorCameraAccess::camera();
        $name = (string) config('door_camera.go2rtc_stream_name', 'qlbh_door_camera');
        $base = rtrim((string) config('door_camera.go2rtc_api_url'), '/');
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $name) || $base === '') {
            return false;
        }

        $apiUrl = $base.'/api/streams';
        if (preg_match('#^rtsps?://#i', $camera['snapshot_url']) !== 1) {
            $transcodedName = (string) config('door_camera.go2rtc_webrtc_stream_name', $name.'_webrtc');
            foreach (array_unique([$name, $transcodedName]) as $staleName) {
                try {
                    $this->go2rtcRequest()->delete($apiUrl.'?'.http_build_query(['src' => $staleName, 'persist' => 'false']));
                } catch (\Throwable) {
                    // No stream to clean up.
                }
                Cache::forget('door_camera.go2rtc.source_hash.'.$staleName);
            }

            return false;
        }

        return $this->syncGo2rtcNamedStream($name, self::rtspSource($camera), $force);
    }

    /** Register a named source without exposing it to the browser. */
    private function syncGo2rtcNamedStream(string $name, string $source, bool $force): bool
    {
        $base = rtrim((string) config('door_camera.go2rtc_api_url'), '/');
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $name) || $base === '') {
            return false;
        }

        try {
            $apiUrl = $base.'/api/streams';
            $existing = $this->go2rtcRequest()->get($apiUrl);
            $hasStream = $existing->successful() && array_key_exists($name, (array) $existing->json());
            $sourceHash = hash('sha256', $source);
            $cacheKey = 'door_camera.go2rtc.source_hash.'.$name;
            if ($hasStream && ! $force && Cache::get($cacheKey) === $sourceHash) {
                return true;
            }

            $url = $apiUrl.'?'.http_build_query(['name' => $name, 'src' => $source, 'persist' => 'false']);
            $response = $hasStream
                ? $this->go2rtcRequest()->patch($url)
                : $this->go2rtcRequest()->put($url);
            if ($response->successful()) {
                Cache::put($cacheKey, $sourceHash, 86400);
            }

            return $response->successful();
        } catch (\Throwable $error) {
            report($error);

            return false;
        }
    }

    private function requestWebrtcOffer(string $stream, string $offer): \Illuminate\Http\Client\Response
    {
        $url = rtrim((string) config('door_camera.go2rtc_api_url'), '/').'/api/webrtc?'.http_build_query(['src' => $stream]);

        return $this->go2rtcRequest()->timeout(20)->withBody($offer, 'application/sdp')->post($url);
    }

    private function go2rtcRequest(): PendingRequest
    {
        $request = Http::timeout(8)->accept('*/*');
        $username = (string) config('door_camera.go2rtc_api_username', '');
        if ($username !== '') {
            $request = $request->withBasicAuth($username, (string) config('door_camera.go2rtc_api_password', ''));
        }

        return $request;
    }

    /** Add camera credentials saved in QLBH settings without exposing them to clients. */
    private static function rtspSource(array $camera): string
    {
        $parts = parse_url($camera['snapshot_url']);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || isset($parts['user'])) {
            return $camera['snapshot_url'];
        }

        $credentials = '';
        if ($camera['username'] !== '') {
            $credentials = rawurlencode($camera['username']).':'.rawurlencode($camera['password']).'@';
        }
        $authority = $credentials.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $parts['scheme'].'://'.$authority.$path.$query;
    }

    private static function liveDataPath(string $name): string
    {
        return storage_path(self::LIVE_DIRECTORY.'/'.$name);
    }

    private static function writeLiveData(string $name, string $contents): bool
    {
        $directory = storage_path(self::LIVE_DIRECTORY);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        $handle = @fopen(self::liveDataPath($name), 'c+b');
        if ($handle === false) {
            return false;
        }

        try {
            if (! flock($handle, LOCK_EX) || ! ftruncate($handle, 0) || ! rewind($handle)) {
                return false;
            }

            $length = strlen($contents);
            $written = 0;
            while ($written < $length) {
                $count = fwrite($handle, substr($contents, $written));
                if ($count === false || $count === 0) {
                    return false;
                }
                $written += $count;
            }

            return fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function readLiveData(string $name): ?string
    {
        $handle = @fopen(self::liveDataPath($name), 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            if (! flock($handle, LOCK_SH)) {
                return null;
            }

            $contents = stream_get_contents($handle);

            return $contents === false ? null : $contents;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function facesWanted(): bool
    {
        $path = self::liveDataPath('viewers.lease');
        if (! is_file($path)) {
            return false;
        }

        $lastRequestedAt = @filemtime($path);
        return $lastRequestedAt !== false && $lastRequestedAt >= time() - 15;
    }

    private static function markFacesWanted(): void
    {
        $path = self::liveDataPath('viewers.lease');
        $lastRequestedAt = is_file($path) ? @filemtime($path) : false;
        if ($lastRequestedAt !== false && $lastRequestedAt >= time() - 2) {
            return;
        }

        self::writeLiveData('viewers.lease', (string) time());
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

    /** Thông tin camera nhập ở Cài đặt: worker đọc về, không phải khai báo lại trong file .env của worker. */
    public function config(): JsonResponse
    {
        // Keep go2rtc pointed at the latest camera URL. API credentials remain server-side.
        $camera = DoorCameraAccess::camera();
        if (preg_match('#^rtsps?://#i', $camera['snapshot_url']) === 1 && $this->syncGo2rtcSource(true)) {
            $frameUrl = rtrim((string) config('door_camera.go2rtc_worker_frame_url', 'http://127.0.0.1:1984/api/frame.jpeg'), '?&');
            $separator = str_contains($frameUrl, '?') ? '&' : '?';
            $camera['snapshot_url'] = $frameUrl.$separator.http_build_query([
                'src' => (string) config('door_camera.go2rtc_stream_name', 'qlbh_door_camera'),
            ]);
            $camera['auth'] = 'none';
            $camera['username'] = '';
            $camera['password'] = '';
        }

        return response()->json([
            'enabled' => self::enabled(),
            'camera' => $camera,
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

    /** Health check for the Camera settings page; keep the go2rtc API host private. */
    public static function streamStatus(): array
    {
        return Cache::remember('door_camera.go2rtc.health', 3, function (): array {
            $camera = DoorCameraAccess::camera();
            if (preg_match('#^rtsps?://#i', $camera['snapshot_url']) !== 1) {
                return ['ready' => false, 'message' => 'Nhập địa chỉ camera RTSP để bật video trực tiếp.'];
            }

            $ready = app(self::class)->syncGo2rtcSource(false);

            return [
                'ready' => $ready,
                'message' => $ready ? 'API đã kết nối, nguồn camera đã được đăng ký; hình ảnh được xác nhận khi mở video.' : 'Chưa kết nối go2rtc. Hãy khởi động go2rtc trên máy chủ.',
            ];
        });
    }
}
