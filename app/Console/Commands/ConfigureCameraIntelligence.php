<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DoorCameraAccess;
use Illuminate\Console\Command;
use RuntimeException;

class ConfigureCameraIntelligence extends Command
{
    protected $signature = 'camera-intelligence:configure
        {--ai-rtsp= : RTSP main-stream retained as the camera primary source}
        {--view-rtsp= : H.264 sub-stream used by both Camera Intelligence and MediaMTX/WebRTC}
        {--api-url= : Base URL to this QLBH API (normally leave blank on the same machine)}
        {--camera=door-1 : Unique camera id}
        {--name=Camera cua : Camera display name}';

    protected $description = 'Tạo hoặc sửa config.yaml cho MediaMTX + Camera Intelligence từ cấu hình QLBH';

    public function handle(): int
    {
        $path = base_path('camera-intelligence/config.yaml');
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $camera = (string) $this->option('camera');
        if (! preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $camera)) {
            $this->error('Camera id chỉ gồm chữ thường, số, gạch nối hoặc gạch dưới.');

            return self::FAILURE;
        }

        $savedCamera = DoorCameraAccess::camera($camera);
        $savedRtsp = $this->cameraRtsp($savedCamera);
        $existingAi = $this->yamlValue($existing, 'ai_rtsp_url');
        $existingView = $this->yamlValue($existing, 'view_rtsp_url');
        // Settings are the canonical source. Existing YAML only acts as a
        // fallback for a manual/server-only installation.
        $aiRtsp = (string) ($this->option('ai-rtsp') ?: ($savedRtsp !== '' ? $savedRtsp : $existingAi));
        $viewRtsp = (string) ($this->option('view-rtsp') ?: ($savedCamera['view_rtsp_url'] !== '' ? $this->withCredentials($savedCamera['view_rtsp_url'], $savedCamera) : ($this->isExampleRtsp($existingView) ? $this->substreamUrl($aiRtsp) : $existingView)));

        $cameras = collect(DoorCameraAccess::configuredCameras())
            ->mapWithKeys(fn (array $source): array => [$source['id'] => [
                'id' => $source['id'],
                'name' => $source['name'],
                'ai_rtsp_url' => $this->cameraRtsp($source),
                'view_rtsp_url' => $this->withCredentials($source['view_rtsp_url'], $source),
            ]]);

        // CLI options are retained for a headless/manual install. In normal
        // operation Settings owns all camera records and this branch is unused.
        if ($this->option('ai-rtsp') || $this->option('view-rtsp')) {
            if (! str_starts_with($aiRtsp, 'rtsp://') && ! str_starts_with($aiRtsp, 'rtsps://')) {
                $this->error('Thiếu --ai-rtsp hợp lệ. Ví dụ: --ai-rtsp "rtsp://user:pass@camera/main"');

                return self::FAILURE;
            }
            if (! str_starts_with($viewRtsp, 'rtsp://') && ! str_starts_with($viewRtsp, 'rtsps://')) {
                $this->error('Thiếu --view-rtsp H.264 hợp lệ. Ví dụ: --view-rtsp "rtsp://user:pass@camera/sub"');

                return self::FAILURE;
            }
            $cameras[$camera] = [
                'id' => $camera,
                'name' => (string) $this->option('name'),
                'ai_rtsp_url' => $aiRtsp,
                'view_rtsp_url' => $viewRtsp,
            ];
        }

        // A background service must not be taken offline merely because an
        // operator has not completed the newer Settings form yet. Retain a
        // previously generated, valid local config as a safe migration
        // fallback; Settings becomes canonical again as soon as it contains a
        // complete camera record.
        if ($cameras->isEmpty()
            && (str_starts_with($existingAi, 'rtsp://') || str_starts_with($existingAi, 'rtsps://'))
            && (str_starts_with($existingView, 'rtsp://') || str_starts_with($existingView, 'rtsps://'))) {
            $cameras[$camera] = [
                'id' => $camera,
                'name' => (string) $this->option('name'),
                'ai_rtsp_url' => $existingAi,
                'view_rtsp_url' => $existingView,
            ];
            $this->warn('Chưa có camera hoàn chỉnh trong Cài đặt; đang giữ cấu hình RTSP cũ hợp lệ để dịch vụ không bị gián đoạn.');
        }

        if ($cameras->isEmpty()) {
            $this->error('Chưa có camera RTSP hoàn chỉnh. Vào Cài đặt → Khách hàng để thêm camera rồi lưu.');

            return self::FAILURE;
        }

        $q = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        try {
            $yaml = "server:\n"
                ."  host: 127.0.0.1\n"
                ."  port: 9070\n"
                ."  event_url: ".$q($this->apiUrl())."\n"
                ."  camera_key: ".$q(DoorCameraAccess::ensureKey())."\n"
                ."  event_timeout_seconds: 4\n\n"
                ."  webrtc_additional_hosts: ".$q($this->webrtcHosts())."\n\n"
                ."inference:\n"
                ."  device: AUTO\n"
                ."  detector_model: models/face_detection_yunet_2023mar.onnx\n"
                ."  recognizer_model: models/face_recognition_sface_2021dec.onnx\n"
                // Camera cửa thường đặt cao; 40px vẫn đủ cho YuNet tạo 5 điểm
                // mốc, còn SFace/quality gate phía sau mới quyết định có nhận
                // diện và lưu lượt khách hay không.
                ."  min_face_px: 40\n"
                // Ten fresh frames a second keeps first sighting responsive
                // while the latest-frame reader prevents a slow HTTP event
                // from turning those frames into a delayed backlog.
                ."  detect_fps: 10\n"
                ."  recognition_cooldown_seconds: 4\n"
                ."  match_threshold: 0.72\n"
                ."  match_margin: 0.08\n\n"
                ."cameras:\n";
            foreach ($cameras->values() as $source) {
                $yaml .= "  - id: ".$q($source['id'])."\n"
                    ."    name: ".$q($source['name'])."\n"
                    ."    enabled: true\n"
                    ."    ai_rtsp_url: ".$q($source['ai_rtsp_url'])."\n"
                    ."    view_rtsp_url: ".$q($source['view_rtsp_url'])."\n"
                    ."    analysis_rtsp_url: ".$q('rtsp://127.0.0.1:8554/'.$source['id'].'-view')."\n"
                    ."    width: 1280\n"
                    ."    height: 720\n"
                    ."    zone: entrance\n";
            }
        } catch (\JsonException $exception) {
            throw new RuntimeException('Không thể tạo config an toàn: '.$exception->getMessage(), previous: $exception);
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $yaml, LOCK_EX);
        $this->info('Đã cập nhật camera-intelligence/config.yaml cho '.$cameras->count().' camera. RTSP và khóa không được in ra màn hình.');
        $this->line('Chạy deploy/camera-platform/install-windows.ps1 trên Windows, hoặc docker compose up -d --build trên Ubuntu.');

        return self::SUCCESS;
    }

    private function yamlValue(string $yaml, string $key): string
    {
        if (! preg_match('/^\s*'.preg_quote($key, '/').'\s*:\s*(.+?)\s*$/m', $yaml, $match)) {
            return '';
        }
        $value = trim($match[1]);
        if (str_starts_with($value, '"')) {
            return (string) json_decode($value, true);
        }

        return preg_replace('/\s+#.*$/', '', $value) ?? '';
    }

    /** @param array{snapshot_url: string, view_rtsp_url: string, auth: string, username: string, password: string} $camera */
    private function cameraRtsp(array $camera): string
    {
        $url = $camera['snapshot_url'];
        if (! str_starts_with($url, 'rtsp://') && ! str_starts_with($url, 'rtsps://')) {
            return '';
        }
        if ($camera['username'] === '' || $camera['password'] === '') {
            return $url;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }
        $authority = rawurlencode($camera['username']).':'.rawurlencode($camera['password']).'@'.$parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':'.$parts['port'];
        }

        return $parts['scheme'].'://'.$authority.($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function isExampleRtsp(string $url): bool
    {
        return $url === '' || str_contains($url, 'user:password@192.168.1.50');
    }

    /** @param array{snapshot_url: string, view_rtsp_url: string, auth: string, username: string, password: string} $camera */
    private function withCredentials(string $url, array $camera): string
    {
        return $this->cameraRtsp([...$camera, 'snapshot_url' => $url]);
    }

    private function substreamUrl(string $url): string
    {
        return str_contains($url, 'subtype=0') ? str_replace('subtype=0', 'subtype=1', $url) : $url;
    }

    private function apiUrl(): string
    {
        $provided = rtrim((string) $this->option('api-url'), '/');
        if ($provided !== '') {
            return $provided;
        }

        // The native Windows worker runs on the same Laragon machine. Calling
        // the loopback HTTP vhost avoids a self-signed LAN HTTPS certificate
        // that Python correctly refuses to trust. Docker/Linux deployments can
        // retain APP_URL or pass --api-url explicitly for their private API.
        if (PHP_OS_FAMILY === 'Windows') {
            return 'http://127.0.0.1/api';
        }

        // Resolve the route from APP_URL. Laragon commonly serves this project
        // from a subdirectory (for example /qlbh/public), which loopback /api
        // alone would miss and therefore return 404 to the background worker.
        return rtrim(url('/api'), '/');
    }

    /** @return array<int, string> */
    private function webrtcHosts(): array
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $hosts = ['127.0.0.1'];
        if (is_string($host) && $host !== '' && strtolower($host) !== 'localhost') {
            $hosts[] = $host;
        }

        return array_values(array_unique($hosts));
    }
}
