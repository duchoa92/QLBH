<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFaceProfile;
use App\Support\DoorCameraAccess;
use App\Support\FaceMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Boundary between the independent Camera Intelligence service and QLBH.
 * It accepts compact metadata only; RTSP/WebRTC remain outside Laravel.
 */
class CameraIntelligenceController extends Controller
{
    private const HEARTBEAT_KEY = 'camera_intelligence.heartbeat';
    private const FACES_KEY = 'camera_intelligence.faces';

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cameras' => ['required', 'array', 'max:64'],
            'cameras.*.id' => ['required', 'string', 'max:64'],
            'cameras.*.running' => ['required', 'boolean'],
            'cameras.*.inference_ready' => ['required', 'boolean'],
            'cameras.*.frames' => ['required', 'integer', 'min:0'],
            'cameras.*.detected_faces' => ['required', 'integer', 'min:0'],
            'cameras.*.last_error' => ['nullable', 'string', 'max:400'],
            'cameras.*.last_frame_at' => ['nullable', 'numeric', 'min:0'],
        ]);

        Cache::put(self::HEARTBEAT_KEY, ['at' => now()->timestamp, 'cameras' => $data['cameras']], 90);

        return response()->json(['ok' => true, 'enabled' => self::enabled()]);
    }

    /** Persist transient overlay labels only; never persist a face vector here. */
    public function faces(Request $request): JsonResponse
    {
        $data = $request->validate([
            'camera_id' => ['required', 'string', 'max:64'],
            'faces' => ['present', 'array', 'max:32'],
            'faces.*.id' => ['required', 'string', 'max:80'],
            'faces.*.x' => ['required', 'numeric', 'between:0,1'],
            'faces.*.y' => ['required', 'numeric', 'between:0,1'],
            'faces.*.w' => ['required', 'numeric', 'between:0,1'],
            'faces.*.h' => ['required', 'numeric', 'between:0,1'],
            'faces.*.kind' => ['required', 'in:known,unknown,scanning'],
            'faces.*.label' => ['nullable', 'string', 'max:80'],
        ]);
        // Overlay labels are ephemeral. A stopped/delayed worker must not
        // leave an old face box on top of a newer empty WebRTC frame.
        Cache::put(self::FACES_KEY.'.'.$data['camera_id'], ['at' => (int) floor(microtime(true) * 1000), 'faces' => array_values($data['faces'])], 3);

        return response()->json(['ok' => true]);
    }

    public function liveFaces(Request $request): JsonResponse
    {
        $camera = (string) $request->query('camera', 'door-1');
        $data = Cache::get(self::FACES_KEY.'.'.$camera, []);

        return response()->json(['at' => $data['at'] ?? 0, 'faces' => $data['faces'] ?? []]);
    }

    public function live(): JsonResponse
    {
        return response()->json([
            'ready' => self::status()['online'],
            'camera' => (string) request()->query('camera', 'door-1'),
            'message' => self::status()['online'] ? null : 'Camera Intelligence chưa gửi heartbeat.',
        ]);
    }

    /** Laravel proxy for WHEP signalling. Media packets still travel directly from MediaMTX to the browser. */
    public function webrtcOffer(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $offer = (string) $request->getContent();
        if (strlen($offer) > 262144 || ! str_starts_with(ltrim($offer), 'v=0')) {
            return response()->json(['message' => 'Đề nghị WebRTC không hợp lệ.'], 422);
        }
        $camera = (string) $request->query('camera', 'door-1');
        if (! preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $camera)) {
            return response()->json(['message' => 'Camera không hợp lệ.'], 422);
        }
        try {
            $response = Http::timeout(12)->withBody($offer, 'application/sdp')->post(rtrim((string) config('camera_intelligence.mediamtx_webrtc_url'), '/').'/'.$camera.'-view/whep');
        } catch (\Throwable) {
            return response()->json(['message' => 'Không gọi được MediaMTX. Kiểm tra dịch vụ camera platform.'], 502);
        }
        if (! $response->successful()) {
            return response()->json(['message' => 'MediaMTX chưa sẵn sàng phát luồng H.264 của camera.'], 502);
        }

        return response($response->body(), $response->status(), ['Content-Type' => $response->header('Content-Type', 'application/sdp'), 'Cache-Control' => 'no-store']);
    }

    /**
     * The service submits one normalized SFace descriptor for a quality-gated track.
     * Matching remains in QLBH, so face banks never leave the application database.
     */
    public function match(Request $request): JsonResponse
    {
        $data = $request->validate([
            'descriptors' => ['required', 'array', 'min:1', 'max:3'],
            'descriptors.*' => ['required', 'array', 'size:'.FaceMatcher::SFACE_SIZE],
            'descriptors.*.*' => ['required', 'numeric', 'between:-'.FaceMatcher::VALUE_LIMIT.','.FaceMatcher::VALUE_LIMIT],
        ]);
        $probes = array_map(fn (array $descriptor) => array_map('floatval', $descriptor), $data['descriptors']);
        $scores = [];
        CustomerFaceProfile::query()->where('engine', FaceMatcher::SFACE)->select(['id', 'customer_id', 'descriptor'])->chunkById(500, function ($profiles) use (&$scores, $probes): void {
            foreach ($profiles->groupBy('customer_id') as $customerId => $samples) {
                $perProbe = [];
                foreach ($probes as $probe) {
                    $perProbe[] = $samples->map(fn ($profile) => FaceMatcher::similarity($probe, $profile->descriptor, FaceMatcher::SFACE))->max();
                }
                rsort($perProbe);
                $scores[(int) $customerId] = array_sum(array_slice($perProbe, 0, max(1, (int) ceil(count($perProbe) / 2)))) / max(1, (int) ceil(count($perProbe) / 2));
            }
        });
        arsort($scores);
        $ids = array_keys($scores);
        $best = $ids === [] ? 0.0 : (float) $scores[$ids[0]];
        $gap = isset($ids[1]) ? $best - (float) $scores[$ids[1]] : 1.0;
        if ($best < FaceMatcher::threshold(FaceMatcher::SFACE) || $gap < FaceMatcher::margin(FaceMatcher::SFACE)) {
            return response()->json(['matched' => false, 'ambiguous' => $best >= FaceMatcher::threshold(FaceMatcher::SFACE), 'score' => round($best, 3)]);
        }
        $customer = Customer::query()->find($ids[0], ['id', 'full_name']);

        return response()->json(['matched' => $customer !== null, 'score' => round($best, 3), 'customer' => $customer]);
    }

    public static function status(): array
    {
        $data = Cache::get(self::HEARTBEAT_KEY);
        $online = is_array($data) && ($data['at'] ?? 0) >= now()->timestamp - 30;
        $cameras = $online ? ($data['cameras'] ?? []) : [];
        $runtime = collect($cameras)->keyBy(fn (array $camera) => (string) ($camera['id'] ?? ''));
        $error = collect($cameras)->pluck('last_error')->filter()->first();
        $sources = DoorCameraAccess::publicCameras();
        $sourceSignature = collect($sources)->map(fn (array $camera) => implode('|', [
            $camera['id'], $camera['enabled'] ? '1' : '0', $camera['snapshot_url'], $camera['view_rtsp_url'],
        ]))->implode('\n');
        $configuredCameras = array_values(array_map(function (array $camera) use ($runtime): array {
            $worker = $runtime->get($camera['id'], []);
            $lastFrameAt = (float) ($worker['last_frame_at'] ?? 0);
            $receiving = (bool) ($worker['running'] ?? false)
                && (bool) ($worker['inference_ready'] ?? false)
                && $lastFrameAt >= now()->timestamp - 15;

            return [
                'id' => $camera['id'],
                'name' => $camera['name'],
                'receiving' => $receiving,
                'frames' => (int) ($worker['frames'] ?? 0),
                'message' => $worker['last_error'] ?? ($receiving ? null : 'Chưa nhận được khung hình từ RTSP.'),
            ];
        }, array_filter($sources, static fn (array $camera): bool => $camera['enabled'] && $camera['configured'])));
        // The overall camera section remains usable if at least one camera is
        // receiving frames. Each individual camera below carries its own state.
        $ready = $online && collect($configuredCameras)->contains(fn (array $camera): bool => $camera['receiving']);
        $error ??= collect($configuredCameras)->pluck('message')->filter()->first();

        return [
            'online' => $online,
            'camera_ok' => $ready,
            'cameras' => $cameras,
            'message' => $error,
            'seconds_ago' => is_array($data) ? now()->timestamp - $data['at'] : null,
            // Browser-safe list and individual runtime state for the camera
            // switcher. RTSP URLs and credentials are never returned here.
            'configured_cameras' => $configuredCameras,
            // Used by WebRTC consumers to decide whether a peer needs a fresh
            // offer. It changes only when an RTSP source is added/changed,
            // never when heartbeat counters or frame counts advance.
            'source_hash' => substr(hash('sha256', $sourceSignature), 0, 16),
            // WHEP signalling is intentionally direct so a blocked/slow media
            // negotiation can never occupy Laravel/PHP workers that receive
            // the independent AI heartbeat and recognition events.
            'webrtc_url' => (string) config('camera_intelligence.mediamtx_browser_webrtc_url'),
        ];
    }

    public static function enabled(): bool
    {
        return filter_var(setting('face_recognition_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(setting('face_visit_tracking_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}
