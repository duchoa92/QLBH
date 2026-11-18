<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Api\CameraIntelligenceController;
use App\Models\Customer;
use App\Models\CustomerFaceProfile;
use App\Models\CustomerVisit;
use App\Support\FaceMatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thống kê lượt khách ra vào cửa hàng (dựa trên camera POS) và gán tên cho khách lạ sau.
 *
 * - POS ghi 1 lượt cho mỗi người xuất hiện trước camera (khách quen được nhận ra, khách lạ vẫn được chụp ảnh nhỏ).
 * - Khách lạ có vector đặc trưng để gộp các lần thấy và gán tên sau; vector/ảnh chỉ giữ trong thời hạn cài đặt.
 * - Chỉ hoạt động khi chủ cửa hàng bật "Ghi nhận lượt khách" trong Cài đặt → Khách hàng.
 */
class CustomerVisitController extends Controller
{
    /** Cùng một khách quen quay lại trong khoảng này được tính là cùng một lượt. */
    private const KNOWN_WINDOW_MINUTES = 30;

    /** Khách lạ thấy lại trong khoảng này và giống nhau thì gộp vào một lượt. */
    private const UNKNOWN_WINDOW_HOURS = 6;

    private const MAX_PROFILE_SAMPLES = 10;

    /* ------------------------------------------------------------------ */
    /* TRANG THỐNG KÊ                                                       */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): Response
    {
        $range = in_array($request->query('range'), ['today', 'yesterday', '7d', '30d', 'custom'], true) ? $request->query('range') : 'today';
        [$from, $to] = $this->resolveRange($range, $request->query('date_from'), $request->query('date_to'));
        $status = in_array($request->query('status'), ['known', 'unknown', 'assigned'], true) ? $request->query('status') : '';

        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 20;

        $base = CustomerVisit::query()->whereBetween('first_seen_at', [$from, $to]);

        $summary = (clone $base)->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN recognized = 1 THEN 1 ELSE 0 END) as known,
             SUM(CASE WHEN recognized = 0 AND customer_id IS NULL THEN 1 ELSE 0 END) as unknown,
             SUM(CASE WHEN recognized = 0 AND customer_id IS NOT NULL THEN 1 ELSE 0 END) as assigned,
             COUNT(DISTINCT customer_id) as distinct_customers'
        )->first();

        // Theo giờ trong ngày (giờ theo múi giờ ứng dụng) và theo ngày: gom ở PHP để không phụ thuộc loại CSDL.
        $hours = array_fill(0, 24, 0);
        $days = [];
        (clone $base)->orderBy('first_seen_at')->pluck('first_seen_at')->each(function ($at) use (&$hours, &$days): void {
            $hours[(int) $at->format('G')]++;
            $key = $at->format('Y-m-d');
            $days[$key] = ($days[$key] ?? 0) + 1;
        });

        $dayList = [];
        for ($cursor = $from->copy()->startOfDay(); $cursor->lte($to); $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $dayList[] = ['date' => $key, 'label' => $cursor->format('d/m'), 'count' => $days[$key] ?? 0];
            if (count($dayList) >= 92) {
                break;
            }
        }

        // Một khách đã xác định có thể ghé nhiều lần trong khoảng lọc, nhưng màn hình
        // thống kê chỉ hiển thị một block. Khách chưa gán tên vẫn giữ từng block riêng
        // vì chưa thể khẳng định hai khuôn mặt là cùng người sau khi dữ liệu đã hết hạn.
        $visits = (clone $base)
            ->selectRaw('MIN(id) as id, customer_id, MAX(recognized) as recognized, MAX(thumb) as thumb, MIN(first_seen_at) as first_seen_at, MAX(last_seen_at) as last_seen_at, COUNT(*) as visit_count, MAX(CASE WHEN descriptor IS NOT NULL THEN 1 ELSE 0 END) as has_face')
            ->with('customer:id,code,full_name,phone')
            ->when($status === 'known', fn ($q) => $q->where('recognized', true))
            ->when($status === 'unknown', fn ($q) => $q->where('recognized', false)->whereNull('customer_id'))
            ->when($status === 'assigned', fn ($q) => $q->where('recognized', false)->whereNotNull('customer_id'))
            ->groupByRaw('CASE WHEN customer_id IS NULL THEN id ELSE -customer_id END, customer_id')
            ->orderByDesc('last_seen_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CustomerVisit $visit) => [
                'id' => $visit->id,
                'recognized' => $visit->recognized,
                'thumb' => $visit->thumb,
                'has_face' => (bool) $visit->has_face,
                'first_seen_at' => $visit->first_seen_at?->toIso8601String(),
                'last_seen_at' => $visit->last_seen_at?->toIso8601String(),
                'minutes' => max(0, (int) round($visit->first_seen_at->diffInSeconds($visit->last_seen_at) / 60)),
                'visit_count' => (int) $visit->visit_count,
                'present' => $visit->last_seen_at?->gte(now()->subSeconds((int) config('door_camera.present_seconds', 20))) ?? false,
                'customer' => $visit->customer ? [
                    'id' => $visit->customer->id,
                    'code' => $visit->customer->code,
                    'full_name' => $visit->customer->full_name,
                    'phone' => $visit->customer->phone,
                ] : null,
            ]);

        return Inertia::render('CustomerVisits/Index', [
            'filters' => [
                'range' => $range,
                'status' => $status,
                'per_page' => $perPage,
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'known' => (int) ($summary->known ?? 0),
                'unknown' => (int) ($summary->unknown ?? 0),
                'assigned' => (int) ($summary->assigned ?? 0),
                'distinct_customers' => (int) ($summary->distinct_customers ?? 0),
            ],
            'hours' => $hours,
            'days' => $dayList,
            'visits' => $visits,
            'trackingEnabled' => $this->trackingEnabled(),
            'doorCamera' => [
                'enabled' => filter_var(setting('face_door_camera_enabled', false), FILTER_VALIDATE_BOOLEAN),
            ] + CameraIntelligenceController::status(),
            'retentionDays' => $this->retentionDays(),
            'canManage' => (bool) $request->user()?->can('customers.edit'),
            'canCreateCustomer' => (bool) $request->user()?->can('customers.create'),
        ]);
    }

    /**
     * Lịch sử của người trong một lượt khách bấm vào: khách đã có tên thì mọi lượt của khách đó; khách chưa có tên thì
     * các lượt khác có khuôn mặt giống (còn vector trong thời hạn lưu), không có vector thì chỉ có chính lượt này.
     */
    public function history(CustomerVisit $visit): JsonResponse
    {
        $visit->loadMissing('customer:id,code,full_name,phone');
        $limit = 100;

        if ($visit->customer_id) {
            $query = CustomerVisit::query()->where('customer_id', $visit->customer_id);
            $total = (clone $query)->count();
            $rows = $query->orderByDesc('first_seen_at')->limit($limit)->get();
            $matchedBy = 'customer';
        } elseif ($visit->descriptor) {
            $probe = array_map('floatval', $visit->descriptor);
            $engine = $visit->descriptor_engine ?: FaceMatcher::HUMAN;
            $ids = CustomerVisit::query()
                ->whereNull('customer_id')
                ->whereNotNull('descriptor')
                ->where('descriptor_engine', $engine)
                ->where('id', '!=', $visit->id)
                ->get(['id', 'descriptor'])
                ->filter(fn (CustomerVisit $other) => FaceMatcher::isSamePerson($probe, $other->descriptor, $engine))
                ->pluck('id')
                ->push($visit->id);
            $total = $ids->count();
            $rows = CustomerVisit::query()->whereIn('id', $ids)->orderByDesc('first_seen_at')->limit($limit)->get();
            $matchedBy = 'face';
        } else {
            $total = 1;
            $rows = collect([$visit]);
            $matchedBy = 'single';
        }

        $minutesOf = fn (CustomerVisit $row) => max(0, (int) round($row->first_seen_at->diffInSeconds($row->last_seen_at) / 60));
        $totalMinutes = $rows->sum($minutesOf);

        return response()->json([
            'person' => [
                'customer_id' => $visit->customer?->id,
                'name' => $visit->customer?->full_name,
                'code' => $visit->customer?->code,
                'phone' => $visit->customer?->phone,
                'recognized' => $visit->recognized,
                'thumb' => $visit->thumb ?? $rows->firstWhere('thumb', '!=', null)?->thumb,
            ],
            'matched_by' => $matchedBy,
            'stats' => [
                'count' => $total,
                'days' => $rows->map(fn (CustomerVisit $row) => $row->first_seen_at->format('Y-m-d'))->unique()->count(),
                'first_at' => $rows->min('first_seen_at')?->toIso8601String(),
                'last_at' => $rows->max('last_seen_at')?->toIso8601String(),
                'avg_minutes' => $rows->count() ? (int) round($totalMinutes / $rows->count()) : 0,
            ],
            'visits' => $rows->map(fn (CustomerVisit $row) => [
                'id' => $row->id,
                'current' => $row->id === $visit->id,
                'recognized' => $row->recognized,
                'thumb' => $row->thumb,
                'first_seen_at' => $row->first_seen_at?->toIso8601String(),
                'last_seen_at' => $row->last_seen_at?->toIso8601String(),
                'minutes' => $minutesOf($row),
            ])->values(),
            'truncated' => $total > $rows->count(),
        ]);
    }

    public function destroy(CustomerVisit $visit): JsonResponse
    {
        $visit->delete();

        return response()->json(['deleted' => true]);
    }

    /* ------------------------------------------------------------------ */
    /* API TỪ POS                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Ai đang đứng ở cửa (camera cửa/worker ghi lượt liên tục): lượt có nhịp trong ít giây gần nhất và chưa quầy nào phục vụ.
     * POS gọi định kỳ thay vì tự mở camera. Không cần camera hay trình duyệt nào khác đang mở.
     */
    public function present(): JsonResponse
    {
        $since = now()->subSeconds((int) config('door_camera.present_seconds', 20));

        $visits = CustomerVisit::query()
            ->where('last_seen_at', '>=', $since)
            ->whereNull('served_at')
            ->with('customer:id,full_name,phone,cccd,debt_balance')
            ->orderBy('first_seen_at')
            ->limit(12)
            ->get()
            ->map(fn (CustomerVisit $visit) => [
                'id' => $visit->id,
                'kind' => $visit->customer ? 'known' : 'unknown',
                'thumb' => $visit->thumb,
                'first_seen_at' => $visit->first_seen_at?->toIso8601String(),
                'customer' => $visit->customer,
            ])
            ->values();

        $status = CameraIntelligenceController::status();

        return response()->json([
            'visits' => $visits,
            'worker_online' => $status['online'],
            'camera_ok' => $status['camera_ok'],
            'source_hash' => $status['source_hash'],
        ]);
    }

    /** Một quầy đã phục vụ lượt này: các quầy khác thôi gợi ý. */
    public function served(Request $request, CustomerVisit $visit): JsonResponse
    {
        if ($visit->served_at === null) {
            $visit->update(['served_at' => now(), 'served_by' => $request->user()?->id]);
        }

        return response()->json(['ok' => true]);
    }

    /** Ghi 1 lượt khách (khách quen hoặc khách lạ). Trả về id lượt để POS liên kết khi nhập thông tin khách. */
    public function record(Request $request): JsonResponse
    {
        if (! $this->trackingEnabled()) {
            return response()->json(['recorded' => false]);
        }

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'engine' => ['nullable', 'in:human-v1,sface-v1,arcface-v1'],
            'descriptor' => ['nullable', 'array', 'min:'.FaceMatcher::MIN_SIZE, 'max:'.FaceMatcher::MAX_SIZE],
            'descriptor.*' => ['required', 'numeric', 'between:-'.FaceMatcher::VALUE_LIMIT.','.FaceMatcher::VALUE_LIMIT],
            'thumb' => ['nullable', 'string', 'max:30000', 'regex:/^data:image\/jpeg;base64,[A-Za-z0-9+\/=]+$/'],
        ]);

        $this->pruneExpired();
        $now = now();

        // Khách quen: cùng người trong 30 phút chỉ tính một lượt.
        if (! empty($data['customer_id'])) {
            $visit = CustomerVisit::query()
                ->where('customer_id', $data['customer_id'])
                ->where('last_seen_at', '>=', $now->copy()->subMinutes(self::KNOWN_WINDOW_MINUTES))
                ->latest('last_seen_at')
                ->first();

            if ($visit) {
                $visit->update(['last_seen_at' => $now]);

                return response()->json(['recorded' => true, 'id' => $visit->id, 'customer_id' => $visit->customer_id]);
            }

            $visit = CustomerVisit::query()->create([
                'customer_id' => $data['customer_id'],
                'recognized' => true,
                'thumb' => $data['thumb'] ?? null,
                'user_id' => $request->user()?->id,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            return response()->json(['recorded' => true, 'id' => $visit->id, 'customer_id' => $visit->customer_id]);
        }

        // Khách lạ: cần vector để gộp các lần thấy lại cùng một người.
        if (empty($data['descriptor'])) {
            return response()->json(['recorded' => false], 422);
        }
        $engine = (string) ($data['engine'] ?? FaceMatcher::HUMAN);
        if (! FaceMatcher::validDescriptor($data['descriptor'], $engine)) {
            $need = $engine === FaceMatcher::SFACE ? FaceMatcher::SFACE_SIZE : 'từ '.FaceMatcher::MIN_SIZE.' đến '.FaceMatcher::MAX_SIZE;
            throw ValidationException::withMessages(['descriptor' => "Vector {$engine} phải có {$need} số."]);
        }
        $probe = array_map('floatval', $data['descriptor']);

        $existing = CustomerVisit::query()
            ->whereNull('customer_id')
            ->whereNotNull('descriptor')
            ->where('descriptor_engine', $engine)
            ->where('last_seen_at', '>=', $now->copy()->subHours(self::UNKNOWN_WINDOW_HOURS))
            ->get(['id', 'descriptor'])
            ->first(fn (CustomerVisit $visit) => FaceMatcher::isSamePerson($probe, $visit->descriptor, $engine));

        if ($existing) {
            $existing->update(['last_seen_at' => $now]);

            return response()->json(['recorded' => true, 'id' => $existing->id, 'customer_id' => null]);
        }

        $visit = CustomerVisit::query()->create([
            'customer_id' => null,
            'recognized' => false,
            'descriptor' => $probe,
            'descriptor_engine' => $engine,
            'thumb' => $data['thumb'] ?? null,
            'user_id' => $request->user()?->id,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);

        return response()->json(['recorded' => true, 'id' => $visit->id, 'customer_id' => null]);
    }

    /** Khách rời khỏi vùng camera: cập nhật giờ ra (dùng để tính thời gian ở lại). */
    public function left(CustomerVisit $visit): JsonResponse
    {
        $visit->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Gán khách lạ cho một khách hàng (đã có hoặc vừa tạo). Chỉ khi khách ĐỒNG Ý lưu khuôn mặt mới đăng ký vector để
     * lần sau tự nhận ra, đồng thời gán luôn các lượt khác của đúng người này.
     */
    public function link(Request $request, CustomerVisit $visit): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'consent' => ['nullable', 'boolean'],
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        $consent = (bool) ($data['consent'] ?? false);
        $linked = 0;

        DB::transaction(function () use ($visit, $customer, $consent, $request, &$linked): void {
            $descriptor = $visit->descriptor;
            $related = collect([$visit]);

            if ($descriptor) {
                $probe = array_map('floatval', $descriptor);
                $engine = $visit->descriptor_engine ?: FaceMatcher::HUMAN;
                $related = $related->merge(
                    CustomerVisit::query()
                        ->whereNull('customer_id')
                        ->whereNotNull('descriptor')
                        ->where('descriptor_engine', $engine)
                        ->where('id', '!=', $visit->id)
                        ->get(['id', 'descriptor'])
                        ->filter(fn (CustomerVisit $other) => FaceMatcher::isSamePerson($probe, $other->descriptor, $engine))
                );

                if ($consent && CustomerFaceProfile::query()->where('customer_id', $customer->id)->where('engine', $engine)->count() < self::MAX_PROFILE_SAMPLES) {
                    CustomerFaceProfile::query()->create([
                        'customer_id' => $customer->id,
                        'descriptor' => $probe,
                        'engine' => $engine,
                        'source' => 'visit',
                        'consented_at' => now(),
                        'consented_by' => $request->user()?->id,
                    ]);
                }
            }

            // Đã gán tên thì không cần giữ vector nữa (kể cả khi khách không đồng ý đăng ký khuôn mặt).
            $linked = CustomerVisit::query()
                ->whereIn('id', $related->pluck('id'))
                ->update(['customer_id' => $customer->id, 'descriptor' => null, 'descriptor_engine' => null]);
        });

        return response()->json(['linked' => $linked, 'customer_id' => $customer->id]);
    }

    /* ------------------------------------------------------------------ */
    /* HỖ TRỢ                                                               */
    /* ------------------------------------------------------------------ */

    private function trackingEnabled(): bool
    {
        return filter_var(setting('face_recognition_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(setting('face_visit_tracking_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function retentionDays(): int
    {
        return min(365, max(1, (int) setting('face_visit_retention_days', 30)));
    }

    /** Xóa ảnh nhỏ và vector của các lượt quá hạn (giữ lại số liệu thống kê), tối đa 1 lần mỗi giờ. */
    private function pruneExpired(): void
    {
        Cache::remember('customer-visits-pruned', 3600, function (): bool {
            CustomerVisit::query()
                ->where('last_seen_at', '<', now()->subDays($this->retentionDays()))
                ->where(fn ($q) => $q->whereNotNull('thumb')->orWhereNotNull('descriptor'))
                ->update(['thumb' => null, 'descriptor' => null, 'descriptor_engine' => null]);

            return true;
        });
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveRange(string $range, mixed $dateFrom, mixed $dateTo): array
    {
        $today = now()->startOfDay();

        return match ($range) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()->endOfDay()],
            '7d' => [$today->copy()->subDays(6), now()->endOfDay()],
            '30d' => [$today->copy()->subDays(29), now()->endOfDay()],
            'custom' => $this->customRange($dateFrom, $dateTo, $today),
            default => [$today, now()->endOfDay()],
        };
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function customRange(mixed $dateFrom, mixed $dateTo, Carbon $today): array
    {
        try {
            $from = $dateFrom ? Carbon::parse((string) $dateFrom)->startOfDay() : $today->copy();
            $to = $dateTo ? Carbon::parse((string) $dateTo)->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            return [$today, now()->endOfDay()];
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365)->startOfDay();
        }

        return [$from, $to];
    }
}
