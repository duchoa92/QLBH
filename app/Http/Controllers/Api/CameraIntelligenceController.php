<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFaceProfile;
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
        Cache::put(self::FACES_KEY.'.'.$data['camera_id'], ['at' => (int) floor(microtime(true) * 1000), 'faces' => array_values($data['faces'])], 8);

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
        $ready = $online && collect($cameras)->contains(fn ($camera) => ($camera['running'] ?? false) && ($camera['inference_ready'] ?? false));
        $error = collect($cameras)->pluck('last_error')->filter()->first();

        return [
            'online' => $online,
            'camera_ok' => $ready,
            'cameras' => $cameras,
            'message' => $error,
            'seconds_ago' => is_array($data) ? now()->timestamp - $data['at'] : null,
            'source_hash' => substr(hash('sha256', json_encode($cameras)), 0, 16),
        ];
    }

    public static function enabled(): bool
    {
        return filter_var(setting('face_recognition_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(setting('face_visit_tracking_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}
