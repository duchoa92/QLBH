<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFaceProfile;
use App\Support\FaceMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'cctv_configured' => (bool) preg_match('#^https?://#i', trim((string) setting('face_cctv_snapshot_url', ''))),
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
            return response()->json(['message' => 'Camera đang dùng địa chỉ RTSP nên chỉ worker camera cửa đọc được. Muốn lấy ảnh đăng ký khuôn mặt từ camera, hãy nhập thêm địa chỉ ảnh chụp http của camera.'], 422);
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
     * @return array{customer_id?: int, confidence?: float, ambiguous?: bool, best?: float}
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
            // Độ tin cậy gần đúng để hiển thị gợi ý (0 = vừa đủ ngưỡng, 1 = rất giống).
            'confidence' => round(max(0, ($similarities[0] - $threshold) / max(0.0001, 1 - $threshold)), 2),
        ];
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
