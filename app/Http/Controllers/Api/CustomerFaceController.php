<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFaceProfile;
use App\Support\FaceMatcher;
use App\Support\FaceTuning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nhận diện khách hàng bằng khuôn mặt (chỉ khi khách đã đồng ý).
 *
 * - Trình duyệt và worker camera cửa tính vector đặc trưng bằng Human (không gửi ảnh lên máy chủ).
 * - Máy chủ chỉ lưu vector, so khớp phía server nên vector của khách khác không bao giờ gửi xuống POS.
 */
class CustomerFaceController extends Controller
{
    /** Tối đa 5 ảnh/mẫu đăng ký, mỗi ảnh có thể kèm bản lật gương nên tối đa 10 vector. */
    private const MAX_SAMPLES = 10;

    /** Số người tối đa so khớp trong một lần gọi (nhiều khách cùng đứng trước camera) và số vector tối đa cho mỗi người. */
    private const MAX_FACES = 6;
    private const MAX_PROBES = 3;

    /** Số mẫu tự học (sau khi nhân viên xác nhận ở POS) giữ lại tối đa cho mỗi khách. */
    private const MAX_AUTO_SAMPLES = 5;

    public function status(Customer $customer): JsonResponse
    {
        $profiles = CustomerFaceProfile::query()->where('customer_id', $customer->id)->get(['id', 'consented_at']);

        return response()->json([
            'registered' => $profiles->isNotEmpty(),
            'samples' => $profiles->count(),
            'consented_at' => $profiles->max('consented_at')?->toIso8601String(),
            // Có cấu hình camera an ninh (địa chỉ ảnh chụp) trong Cài đặt hay chưa.
            // Chỉ địa chỉ ảnh chụp http/https mới lấy được ảnh ở đây; địa chỉ RTSP dành cho worker camera cửa.
            // Camera RTSP cũng dùng được khi đã bật chế độ camera cửa (worker gửi ảnh gốc lên máy chủ).
            'cctv_configured' => (bool) preg_match('#^https?://#i', trim((string) setting('face_cctv_snapshot_url', '')))
                || ((bool) preg_match('#^rtsps?://#i', trim((string) setting('face_cctv_snapshot_url', '')))
                    && filter_var(setting('face_door_camera_enabled', false), FILTER_VALIDATE_BOOLEAN)),
        ]);
    }

    public function store(Request $request, Customer $customer): JsonResponse
    {
        if ($disabled = $this->ensureEnabled()) {
            return $disabled;
        }

        $data = $request->validate([
            'consent' => ['required', 'accepted'],
            'descriptors' => ['required', 'array', 'min:1', 'max:'.self::MAX_SAMPLES],
            'descriptors.*' => ['required', 'array', ...$this->sizeRules()],
            'descriptors.*.*' => ['required', 'numeric', $this->valueRule()],
        ], [
            'consent.accepted' => 'Cần có sự đồng ý của khách hàng trước khi lưu dữ liệu khuôn mặt.',
        ]);

        DB::transaction(function () use ($data, $customer, $request): void {
            // Đăng ký lại = thay toàn bộ mẫu cũ.
            CustomerFaceProfile::query()->where('customer_id', $customer->id)->delete();

            foreach ($data['descriptors'] as $descriptor) {
                CustomerFaceProfile::query()->create([
                    'customer_id' => $customer->id,
                    'descriptor' => array_map('floatval', $descriptor),
                    'source' => 'enroll',
                    'consented_at' => now(),
                    'consented_by' => $request->user()?->id,
                ]);
            }
        });

        return response()->json(['registered' => true, 'samples' => count($data['descriptors'])]);
    }

    /**
     * Lấy 1 ảnh chụp hiện tại từ camera an ninh qua địa chỉ HTTP đã cấu hình trong Cài đặt.
     * Máy chủ gọi camera (trình duyệt gọi trực tiếp thường bị chặn CORS/đăng nhập). Chỉ dùng địa chỉ do quản trị
     * cấu hình, người dùng không nhập được địa chỉ tùy ý nên không thể bắt máy chủ truy cập nơi khác.
     * Ảnh chỉ trả về trình duyệt để tính vector, không lưu trên máy chủ.
     */
    public function cctvSnapshot()
    {
        if ($disabled = $this->ensureEnabled()) {
            return $disabled;
        }

        $url = trim((string) setting('face_cctv_snapshot_url', ''));
        if (preg_match('#^rtsps?://#i', $url)) {
            // Camera RTSP: nhờ worker camera cửa gửi 1 ảnh gốc (máy chủ web không đọc được luồng RTSP).
            if (! DoorCameraController::status()['online']) {
                return response()->json(['message' => 'Camera AI chưa chạy nên chưa lấy được ảnh từ camera RTSP. Hãy kiểm tra trạng thái camera stack trong Cài đặt → Khách hàng → Camera cửa.'], 422);
            }
            $jpeg = DoorCameraController::freshSnapshot();
            if ($jpeg === null) {
                return response()->json(['message' => 'Worker chưa gửi được ảnh từ camera cửa. Kiểm tra worker đang chạy bản mới nhất rồi thử lại.'], 502);
            }

            return response($jpeg, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store']);
        }
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return response()->json(['message' => 'Chưa cấu hình địa chỉ ảnh chụp camera an ninh trong Thiết lập → Khách hàng.'], 422);
        }

        $username = (string) setting('face_cctv_username', '');
        $password = '';
        if (filled($encrypted = setting('face_cctv_password'))) {
            try {
                $password = Crypt::decryptString($encrypted);
            } catch (\Throwable) {
                $password = '';
            }
        }
        $auth = (string) setting('face_cctv_auth', 'none');

        try {
            $request = Http::timeout(8)->withoutRedirecting()->accept('image/*');
            if ($username !== '' && $auth === 'digest') {
                $request = $request->withDigestAuth($username, $password);
            } elseif ($username !== '' && $auth === 'basic') {
                $request = $request->withBasicAuth($username, $password);
            }
            $response = $request->get($url);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Không kết nối được camera an ninh. Kiểm tra địa chỉ và mạng nội bộ.'], 502);
        }

        if (! $response->successful()) {
            return response()->json(['message' => "Camera trả về lỗi {$response->status()}. Kiểm tra tài khoản và địa chỉ ảnh chụp."], 502);
        }

        $type = strtolower((string) $response->header('Content-Type'));
        $body = $response->body();
        if (! str_starts_with($type, 'image/') || $body === '' || strlen($body) > 8 * 1024 * 1024) {
            return response()->json(['message' => 'Địa chỉ này không trả về ảnh hợp lệ. Cần địa chỉ ảnh chụp tĩnh (snapshot/JPEG).'], 422);
        }

        return response($body, 200, ['Content-Type' => $type, 'Cache-Control' => 'no-store']);
    }

    /**
     * Nhân viên bấm "Đúng" ở POS: lưu thêm vector vừa nhận diện làm mẫu mới cho khách đó,
     * giúp hệ thống nhận ra khách ở nhiều góc/ánh sáng hơn theo thời gian (giống cách ứng dụng ảnh học từ xác nhận).
     * Chỉ áp dụng cho khách đã đăng ký và đồng ý; vector phải thật sự gần mẫu đã có của đúng khách đó.
     */
    public function feedback(Request $request, Customer $customer): JsonResponse
    {
        if ($disabled = $this->ensureEnabled()) {
            return $disabled;
        }

        $data = $request->validate([
            'descriptor' => ['required', 'array', ...$this->sizeRules()],
            'descriptor.*' => ['required', 'numeric', $this->valueRule()],
        ]);
        $probe = array_map('floatval', $data['descriptor']);

        $profiles = CustomerFaceProfile::query()->where('customer_id', $customer->id)->orderBy('id')->get();
        if ($profiles->isEmpty()) {
            return response()->json(['added' => false, 'reason' => 'not_registered']);
        }

        $best = $profiles->map(fn ($profile) => FaceMatcher::similarity($probe, $profile->descriptor))->max();
        if ($best < (float) config('face.feedback_min_similarity') || $best >= (float) config('face.feedback_duplicate_similarity')) {
            return response()->json(['added' => false, 'reason' => $best >= (float) config('face.feedback_duplicate_similarity') ? 'duplicate' : 'too_far']);
        }

        DB::transaction(function () use ($customer, $probe, $profiles): void {
            CustomerFaceProfile::query()->create([
                'customer_id' => $customer->id,
                'descriptor' => $probe,
                'source' => 'auto',
                // Giữ nguyên thời điểm đồng ý ban đầu của khách.
                'consented_at' => $profiles->max('consented_at'),
                'consented_by' => null,
            ]);

            $autoIds = CustomerFaceProfile::query()->where('customer_id', $customer->id)->where('source', 'auto')->orderByDesc('id')->pluck('id');
            if ($autoIds->count() > self::MAX_AUTO_SAMPLES) {
                CustomerFaceProfile::query()->whereIn('id', $autoIds->slice(self::MAX_AUTO_SAMPLES)->all())->delete();
            }
        });

        return response()->json(['added' => true]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        CustomerFaceProfile::query()->where('customer_id', $customer->id)->delete();

        return response()->json(['registered' => false, 'samples' => 0]);
    }

    /**
     * POS gửi vector của những người đang đứng trước camera, nhận lại khách khớp nhất cho từng người (nếu đủ chắc chắn).
     *
     * Định dạng mới: { faces: [{ key, descriptors: [vector, ...] }] }, tối đa 6 người, mỗi người 1-3 vector lấy từ các khung hình
     * khác nhau. Dùng nhiều khung hình giúp vẫn nhận đúng khi một khung bị mờ. Trả về { results: [{ key, matched, customer, ... }] }.
     * Định dạng cũ { descriptor } vẫn dùng được và trả về một kết quả duy nhất như trước.
     */
    public function match(Request $request): JsonResponse
    {
        if ($disabled = $this->ensureEnabled()) {
            return $disabled;
        }

        $data = $request->validate([
            'descriptor' => ['required_without:faces', 'array', ...$this->sizeRules()],
            'descriptor.*' => ['required', 'numeric', $this->valueRule()],
            'faces' => ['required_without:descriptor', 'array', 'min:1', 'max:'.self::MAX_FACES],
            'faces.*.key' => ['required'],
            'faces.*.descriptors' => ['required', 'array', 'min:1', 'max:'.self::MAX_PROBES],
            'faces.*.descriptors.*' => ['required', 'array', ...$this->sizeRules()],
            'faces.*.descriptors.*.*' => ['required', 'numeric', $this->valueRule()],
        ]);

        $legacy = isset($data['descriptor']);
        $faces = $legacy
            ? [['key' => 0, 'descriptors' => [$data['descriptor']]]]
            : $data['faces'];

        // Các mẫu đã đăng ký, gom theo khách: [customer_id => [vector, ...]]. Chỉ đọc 1 lần cho cả lô.
        $bank = [];
        CustomerFaceProfile::query()->select(['id', 'customer_id', 'descriptor'])->chunkById(500, function ($profiles) use (&$bank): void {
            foreach ($profiles as $profile) {
                $bank[(int) $profile->customer_id][] = $profile->descriptor;
            }
        });

        $outcomes = [];
        $customerIds = [];
        foreach ($faces as $face) {
            $probes = array_map(
                fn ($descriptor) => array_map('floatval', $descriptor),
                $face['descriptors'] ?? [$face]
            );
            $outcome = $this->matchFace($probes, $bank);
            // Chỉ worker camera cửa (gửi X-Camera-Key, nhìn khách qua nhiều khung hình) mới tự học; POS học khi nhân viên bấm "Đúng".
            if ($request->hasHeader('X-Camera-Key')) {
                $this->learnFromMatch($probes, $outcome);
            }
            if (config('face.log_scores')) {
                Log::info('face.match', ['key' => $face['key'], 'best_similarity' => $outcome['best'] ?? null, 'matched' => isset($outcome['customer_id']), 'ambiguous' => ! empty($outcome['ambiguous'])]);
            }
            $outcome['key'] = $face['key'];
            $outcomes[] = $outcome;
            if (isset($outcome['customer_id'])) {
                $customerIds[] = $outcome['customer_id'];
            }
        }

        $customers = Customer::query()
            ->whereIn('id', array_unique($customerIds))
            ->get(['id', 'full_name', 'phone', 'cccd', 'debt_balance'])
            ->keyBy('id');

        $results = array_map(function (array $outcome) use ($customers): array {
            $customer = isset($outcome['customer_id']) ? $customers->get($outcome['customer_id']) : null;
            $result = ['key' => $outcome['key'], 'matched' => $customer !== null];
            if ($customer !== null) {
                $result['customer'] = $customer;
                $result['confidence'] = $outcome['confidence'];
            }
            if (! empty($outcome['ambiguous'])) {
                $result['ambiguous'] = true;
            }

            return $result;
        }, $outcomes);

        if ($legacy) {
            unset($results[0]['key']);

            return response()->json($results[0]);
        }

        return response()->json(['results' => $results]);
    }

    /**
     * So khớp 1 người (nhiều vector từ nhiều khung hình) với toàn bộ khách đã đăng ký.
     * Điểm của mỗi khách = trung bình các độ giống tốt nhất (nửa số vector, làm tròn lên): một khung hình mờ
     * không làm hỏng kết quả, còn kết quả đúng thì phải ổn định qua nhiều khung hình.
     *
     * @param  array<int, array<int, float>>  $probes
     * @param  array<int, array<int, array<int, float>>>  $bank
     * @return array{customer_id?: int, confidence?: float, ambiguous?: bool, best?: float, gap?: float}
     */
    private function matchFace(array $probes, array $bank): array
    {
        $keep = (int) ceil(count($probes) / 2);
        $scores = [];

        foreach ($bank as $customerId => $samples) {
            $bestPerProbe = [];
            foreach ($probes as $probe) {
                $best = 0.0;
                foreach ($samples as $sample) {
                    $best = max($best, FaceMatcher::similarity($probe, $sample));
                }
                $bestPerProbe[] = $best;
            }
            rsort($bestPerProbe);
            $scores[$customerId] = array_sum(array_slice($bestPerProbe, 0, $keep)) / $keep;
        }

        arsort($scores);
        $ids = array_keys($scores);
        $similarities = array_values($scores);
        $threshold = (float) config('face.match_similarity');

        if ($ids === []) {
            return [];
        }
        if ($similarities[0] < $threshold) {
            return ['best' => round($similarities[0], 3)];
        }

        if (isset($similarities[1]) && ($similarities[0] - $similarities[1]) < (float) config('face.margin')) {
            return ['ambiguous' => true, 'best' => round($similarities[0], 3)];
        }

        return [
            'customer_id' => (int) $ids[0],
            'best' => round($similarities[0], 3),
            // Cách biệt với khách giống thứ nhì (1 nếu chỉ có một khách đã đăng ký): dùng để quyết định có tự học hay không.
            'gap' => isset($similarities[1]) ? round($similarities[0] - $similarities[1], 3) : 1.0,
            // Độ tin cậy gần đúng để hiển thị gợi ý (0 = vừa đủ ngưỡng, 1 = rất giống).
            'confidence' => round(max(0, ($similarities[0] - $threshold) / max(0.0001, 1 - $threshold)), 2),
        ];
    }

    /**
     * Tự bổ sung mẫu khuôn mặt cho khách đã được nhận ra, để lần sau nhận đúng hơn ở nhiều góc/ánh sáng.
     * Chỉ học khi RẤT chắc chắn (sai một lần là mẫu sai nằm lại và kéo theo nhận nhầm về sau), cụ thể phải đủ mọi điều kiện:
     *  - khách đã đăng ký và đồng ý; tính năng tự học đang bật (setting face_auto_learn_enabled, mặc định bật);
     *  - có từ 2 khung hình trở lên, độ giống trung bình đạt ngưỡng cao (face.auto_learn_similarity) và hơn hẳn khách thứ nhì;
     *  - TỪNG khung hình đều giống khách này đạt ngưỡng đó (không chỉ trung bình), và đủ gần mẫu đăng ký gốc (chống trôi dần qua các mẫu tự học);
     *  - mẫu mới không trùng mẫu đã có (tránh thêm mẫu thừa); mỗi khách tối đa 1 mẫu mới mỗi khoảng nghỉ (mặc định 2 giờ) và tối đa
     *    MAX_AUTO_SAMPLES mẫu tự học (mẫu cũ nhất bị thay). Mẫu đăng ký gốc không bao giờ bị xóa.
     *
     * @param  array<int, array<int, float>>  $probes  vector các khung hình, khung nét nhất đứng đầu
     * @param  array{customer_id?: int, best?: float, gap?: float}  $outcome
     */
    private function learnFromMatch(array $probes, array $outcome): void
    {
        try {
            $customerId = (int) ($outcome['customer_id'] ?? 0);
            if ($customerId === 0 || count($probes) < 2) {
                return;
            }
            if (! FaceTuning::autoLearnEnabled()) {
                return;
            }

            $minSimilarity = FaceTuning::int('face_auto_learn_min_percent') / 100;
            if (($outcome['best'] ?? 0) < $minSimilarity || ($outcome['gap'] ?? 0) < FaceTuning::int('face_auto_learn_margin_percent') / 100) {
                return;
            }

            $cooldownKey = 'face.auto_learn.'.$customerId;
            if (Cache::has($cooldownKey)) {
                return;
            }

            $profiles = CustomerFaceProfile::query()->where('customer_id', $customerId)->orderBy('id')->get();
            if ($profiles->isEmpty()) {
                return;
            }
            $enrolled = $profiles->where('source', '!=', 'auto');
            $anchorMin = (float) config('face.match_similarity', 0.5) + 0.05;
            $duplicateAt = (float) config('face.auto_learn_duplicate_similarity', 0.93);

            $candidate = null;
            foreach ($probes as $probe) {
                if ($profiles->map(fn ($profile) => FaceMatcher::similarity($probe, $profile->descriptor))->max() < $minSimilarity) {
                    return;
                }
                if ($enrolled->isNotEmpty() && $enrolled->map(fn ($profile) => FaceMatcher::similarity($probe, $profile->descriptor))->max() < $anchorMin) {
                    return;
                }
                if ($candidate === null && $profiles->map(fn ($profile) => FaceMatcher::similarity($probe, $profile->descriptor))->max() < $duplicateAt) {
                    $candidate = $probe;
                }
            }
            if ($candidate === null) {
                return; // mọi khung đều giống hệt mẫu đã có: không có gì mới để học
            }

            DB::transaction(function () use ($customerId, $candidate, $profiles): void {
                CustomerFaceProfile::query()->create([
                    'customer_id' => $customerId,
                    'descriptor' => $candidate,
                    'source' => 'auto',
                    // Giữ nguyên thời điểm đồng ý ban đầu của khách.
                    'consented_at' => $profiles->max('consented_at'),
                    'consented_by' => null,
                ]);

                $autoIds = CustomerFaceProfile::query()->where('customer_id', $customerId)->where('source', 'auto')->orderByDesc('id')->pluck('id');
                if ($autoIds->count() > self::MAX_AUTO_SAMPLES) {
                    CustomerFaceProfile::query()->whereIn('id', $autoIds->slice(self::MAX_AUTO_SAMPLES)->all())->delete();
                }
            });

            Cache::put($cooldownKey, 1, now()->addMinutes(FaceTuning::int('face_auto_learn_cooldown_minutes')));
            Log::info('face.auto_learn', ['customer_id' => $customerId, 'best_similarity' => $outcome['best'] ?? null, 'gap' => $outcome['gap'] ?? null]);
        } catch (\Throwable $e) {
            // Tự học chỉ là phần thêm: lỗi ở đây không được làm hỏng việc nhận diện.
            Log::warning('face.auto_learn_failed', ['message' => $e->getMessage()]);
        }
    }

    /** Tính năng tắt trong Cài đặt thì không đăng ký/so khớp (vẫn cho xem trạng thái và xóa dữ liệu). */
    private function ensureEnabled(): ?JsonResponse
    {
        if (filter_var(setting('face_recognition_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return response()->json(['message' => 'Tính năng nhận diện khuôn mặt đang tắt trong Cài đặt → Khách hàng.'], 403);
    }

    /** @return array<int, string> */
    private function sizeRules(): array
    {
        return ['min:'.FaceMatcher::MIN_SIZE, 'max:'.FaceMatcher::MAX_SIZE];
    }

    private function valueRule(): string
    {
        return 'between:-'.FaceMatcher::VALUE_LIMIT.','.FaceMatcher::VALUE_LIMIT;
    }
}
