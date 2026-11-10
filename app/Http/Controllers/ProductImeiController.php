<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductImeiHistory;
use App\Models\Repair;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Services\Lookup\EntityLookupService;
use App\Services\Stock\ImeiStockSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductImeiController extends Controller
{
    private const LOOKUP_SCOPES = ['all', 'sold', 'in_stock', 'repair', 'customer', 'user', 'supplier', 'sale', 'import', 'product'];

    public function index(
        Request $request,
        Product $product
    ): Response|JsonResponse {

        $product->load('imeis');

        if ($request->expectsJson()) {
            return response()->json(['product' => $product]);
        }

        return Inertia::render(

            'ProductImeis/Index',

            [
                'product' => $product,
            ]
        );
    }

    public function store(
        Request $request,
        Product $product
    ): RedirectResponse {

        $request->validate([

            'imei' => [

                'required',

                'unique:product_imeis,imei',
            ],
        ]);

        ProductImei::query()

            ->create([

                'product_id' =>
                    $product->id,

                'imei' =>
                    $request->imei,

                'status' =>
                    ProductImei::STATUS_AVAILABLE,
            ]);

        app(ImeiStockSynchronizer::class)->sync($product);

        return back();
    }

    public function updatePrice(
        Request $request,
        ProductImei $imei
    ): RedirectResponse {
        if ($imei->status !== ProductImei::STATUS_IN_STOCK) {
            return back()->withErrors([
                'sell_price' => 'Chỉ có thể đổi giá bán cho IMEI còn trong kho.',
            ]);
        }

        $data = $request->validate([
            'sell_price' => ['required', 'numeric', 'min:0'],
        ]);

        $previousPrice = (float) ($imei->sell_price ?? 0);
        $newPrice = (float) $data['sell_price'];

        if ($previousPrice === $newPrice) {
            return back();
        }

        $imei->update(['sell_price' => $newPrice]);

        ProductImeiHistory::create([
            'product_imei_id' => $imei->id,
            'imei' => $imei->imei,
            'type' => 'price_update',
            'reference_type' => ProductImei::class,
            'reference_id' => $imei->id,
            'cost_price' => $imei->cost_price,
            'sell_price' => $newPrice,
            'meta' => [
                'previous_sell_price' => $previousPrice,
                'new_sell_price' => $newPrice,
                'user_id' => auth()->id(),
            ],
            'note' => 'Cập nhật giá bán IMEI',
            'happened_at' => now(),
        ]);

        return back()->with('success', 'Đã cập nhật giá bán IMEI.');
    }

    public function lookupPage(): Response
    {
        return Inertia::render('ProductImeis/Lookup');
    }

    /**
     * Một endpoint, ba chế độ (route hiện có /api/imeis/lookup được giữ nguyên):
     *  - ?imei=...            : kiểm tra khớp chính xác IMEI/Serial (form nhập hàng đang dùng — không đổi).
     *  - ?suggest=1&q=...     : danh sách gợi ý khi gõ (IMEI, khách hàng, máy, thiết bị, mã phiếu...).
     *  - ?q=... | ?id= | ?repair_id= : tra cứu đầy đủ theo từ khóa hoặc theo gợi ý được chọn.
     */
    public function lookup(Request $request): JsonResponse
    {
        if ($request->boolean('suggest')) {
            return $this->suggestions($request);
        }

        if ($request->filled('id') || $request->filled('repair_id') || $request->filled('q')) {
            return $this->searchLookup($request);
        }

        $data = $request->validate([
            'imei' => ['required', 'string', 'max:100'],
        ]);

        $imeiCode = trim($data['imei']);

        return response()->json(
            $this->lookupPayload($this->findImeiByCode($imeiCode), $imeiCode)
        );
    }

    private function findImeiByCode(string $code): ?ProductImei
    {
        return ProductImei::query()
            ->with(['product:id,name,sku,warranty_days', 'variant:id,product_id,sku,attributes'])
            ->where(function ($query) use ($code): void {
                $query
                    ->where('imei', $code)
                    ->orWhere('serial', $code);
            })
            ->first();
    }

    private function lookupPayload(?ProductImei $imei, string $imeiCode, ?Collection $repairIds = null): array
    {
        $saleItems = collect();

        if ($imei) {
            $saleItems = SaleItem::query()
                ->with([
                    'sale:id,code,customer_id,user_id,grand_total,paid_amount,payment_method,status,created_at',
                    'sale.customer:id,full_name,phone',
                    'product:id,name,sku',
                    'variant:id,sku,attributes',
                ])
                ->where('product_imei_id', $imei->id)
                ->latest('id')
                ->get();
        }

        $warranty = $this->warrantyInfo($imei);

        $lookupCodes = collect([
            $imeiCode,
            $imei?->imei,
            $imei?->serial,
        ])
            ->filter()
            ->unique()
            ->values();

        $repairs = Repair::query()
            ->with(['technician:id,name', 'customer:id,full_name,phone'])
            ->where(function ($query) use ($lookupCodes, $repairIds): void {
                $query
                    ->whereIn('imei', $lookupCodes)
                    ->orWhereIn('serial', $lookupCodes);

                if ($repairIds && $repairIds->isNotEmpty()) {
                    $query->orWhereIn('id', $repairIds);
                }
            })
            ->latest('id')
            ->get();

        $importHistories = ProductImeiHistory::query()
            ->where('imei', $imei?->imei ?: $imeiCode)
            ->where('type', 'import')
            ->latest('happened_at')
            ->latest('id')
            ->get();

        // Tên nhà cung cấp của từng lần nhập (trước đây chỉ hiển thị "Nhà cung cấp #id").
        $supplierNames = Supplier::query()
            ->whereIn('id', $importHistories->map(fn (ProductImeiHistory $history) => $history->meta['supplier_id'] ?? null)->filter()->unique()->values())
            ->pluck('name', 'id');

        $latestSale = $saleItems->first()?->sale;
        $latestRepair = $repairs->first();
        $customer = $latestSale?->customer
            ? [
                'name' => $latestSale->customer->full_name,
                'phone' => $latestSale->customer->phone,
                'source' => 'sale',
                'reference_code' => $latestSale->code,
                'happened_at' => $latestSale->created_at,
            ]
            : ($latestRepair && ($latestRepair->customer?->full_name || $latestRepair->customer?->phone || $latestRepair->contact_phone)
                ? [
                    'name' => $latestRepair->customer?->full_name,
                    'phone' => $latestRepair->customer?->phone ?: $latestRepair->contact_phone,
                    'source' => 'repair',
                    'reference_code' => $latestRepair->code,
                    'happened_at' => $latestRepair->received_at ?? $latestRepair->created_at,
                ]
                : null);

        return [
            'exists' => (bool) $imei,
            'can_import' => ! $imei || $imei->status !== ProductImei::STATUS_IN_STOCK,
            'message' => $imei && $imei->status === ProductImei::STATUS_IN_STOCK
                ? 'IMEI này đang còn trong kho, không thể nhập trùng.'
                : ($imei ? 'IMEI đã từng bán hoặc rời kho, có thể nhập lại.' : 'IMEI mới, có thể nhập.'),
            'imei' => $imei ? [
                'id' => $imei->id,
                'imei' => $imei->imei,
                'serial' => $imei->serial,
                'status' => $imei->status,
                'cost_price' => $imei->cost_price,
                'sell_price' => $imei->sell_price,
                'sold_at' => $imei->sold_at,
                'imported_at' => $imei->imported_at,
                'warranty_expired_at' => $imei->warranty_expired_at,
                'extra_info' => $imei->extra_info,
                'product' => $imei->product,
                'variant' => $imei->variant,
            ] : null,
            'warranty' => $warranty,
            'customer_warranty' => $this->customerWarrantyInfo($imei, $saleItems),
            'repair_warranty' => $this->repairWarrantyInfo($repairs),
            'customer' => $customer,
            'summary' => [
                'import_count' => $importHistories->count() ?: ($imei ? 1 : 0),
                'sale_count' => $saleItems->count(),
                'repair_count' => $repairs->count(),
            ],
            'imports' => $importHistories->map(fn (ProductImeiHistory $history) => [
                'id' => $history->id,
                'reference_id' => $history->reference_id,
                'code' => $history->meta['import_code'] ?? null,
                'cost_price' => $history->cost_price,
                'sell_price' => $history->sell_price,
                'supplier_id' => $history->meta['supplier_id'] ?? null,
                'supplier_name' => $supplierNames[$history->meta['supplier_id'] ?? 0] ?? null,
                'happened_at' => $history->happened_at,
                'created_at' => $history->created_at,
            ])->values(),
            'sales' => $saleItems->map(fn (SaleItem $item) => [
                'id' => $item->id,
                'sale_id' => $item->sale?->id,
                'code' => $item->sale?->code,
                'customer_name' => $item->sale?->customer?->full_name,
                'customer_phone' => $item->sale?->customer?->phone,
                'unit_price' => $item->unit_price,
                'discount_value' => $item->discount_value,
                'subtotal' => $item->subtotal,
                'payment_method' => $item->sale?->payment_method,
                'status' => $item->sale?->status,
                'sold_at' => $item->sale?->created_at,
            ])->values(),
            'repairs' => $repairs->map(fn (Repair $repair) => [
                'id' => $repair->id,
                'code' => $repair->code,
                'device_name' => $repair->device_name,
                'contact_phone' => $repair->contact_phone,
                'customer_name' => $repair->customer?->full_name,
                'customer_phone' => $repair->customer?->phone,
                'repair_request' => $repair->repair_request,
                'estimated_cost' => $repair->estimated_cost,
                'final_cost' => $repair->final_cost,
                'status' => $repair->status,
                'technician_name' => $repair->technician?->name,
                'received_at' => $repair->received_at,
                'completed_at' => $repair->completed_at,
                'returned_at' => $repair->returned_at,
                'note' => $repair->note,
                'repair_warranty_days' => $repair->repair_warranty_days,
                'repair_warranty_expires_at' => $repair->repair_warranty_expires_at,
                'repair_warranty_voided_at' => $repair->repair_warranty_voided_at,
            ])->values(),
        ];
    }

    /* ------------------------------------------------------------------
     | Tra cứu theo từ khóa tự do / theo gợi ý được chọn
     |------------------------------------------------------------------*/

    private function noMatchResponse(): JsonResponse
    {
        return response()->json([
            'exists' => false,
            'no_match' => true,
            'message' => 'Không có kết quả phù hợp.',
            'candidates' => [],
        ]);
    }

    /** Phạm vi tìm kiếm: có thể chọn nhiều, truyền dạng "sold,customer,product". Rỗng hoặc có "all" = toàn bộ. */
    private function lookupScopes(Request $request): array
    {
        $scopes = collect(explode(',', (string) $request->query('scope', 'all')))
            ->map(fn ($scope) => trim($scope))
            ->filter(fn ($scope) => in_array($scope, self::LOOKUP_SCOPES, true))
            ->unique()
            ->values();

        return $scopes->isEmpty() || $scopes->contains('all') ? ['all'] : $scopes->all();
    }

    private function allowed(Request $request, string $permission): bool
    {
        $user = $request->user();

        return $user ? $user->can($permission) : false;
    }

    private function searchLookup(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['nullable', 'integer'],
            'repair_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $type = (string) $request->query('type', '');

        // Chọn một kết quả cụ thể (thiết bị, phiếu sửa, khách hàng, sản phẩm, hóa đơn...).
        if ($request->filled('repair_id')) {
            return $this->resolveItem(['type' => 'repair', 'repair_id' => (int) $request->query('repair_id')], $request);
        }

        if ($request->filled('id')) {
            $filter = (string) $request->query('status', '');

            return $this->resolveItem([
                'type' => $type !== '' ? $type : 'imei',
                'id' => (int) $request->query('id'),
                'filter_status' => in_array($filter, [ProductImei::STATUS_SOLD, ProductImei::STATUS_IN_STOCK], true) ? $filter : null,
            ], $request);
        }

        $keyword = trim((string) $request->query('q'));
        $scopes = $this->lookupScopes($request);

        if ($keyword === '') {
            return $this->noMatchResponse();
        }

        // Khớp chính xác IMEI / Serial luôn được ưu tiên — nhưng vẫn phải đúng phạm vi đang chọn.
        if ($this->allowed($request, 'products.view') && ($exact = $this->findImeiByCode($keyword))) {
            $allowed = collect($scopes)->contains(fn (string $scope) => match ($scope) {
                'all' => true,
                'sold' => $exact->status === ProductImei::STATUS_SOLD,
                'in_stock' => $exact->status === ProductImei::STATUS_IN_STOCK,
                'repair' => Repair::query()->where(fn ($q) => $q->where('imei', $exact->imei)
                    ->orWhere('serial', $exact->serial ?: $exact->imei))->exists(),
                default => false,
            });

            if ($allowed) {
                return $this->selectionResponse($exact, null);
            }
        }

        $items = $this->scopeSuggestions($keyword, $scopes, 20, $request);

        if ($items->isEmpty()) {
            return $this->noMatchResponse();
        }

        if ($items->count() === 1) {
            return $this->resolveItem($items->first(), $request);
        }

        return response()->json([
            'exists' => false,
            'multiple' => true,
            'message' => "Tìm thấy {$items->count()} kết quả phù hợp, vui lòng chọn một kết quả.",
            'candidates' => $items->values(),
        ]);
    }

    /** Mở chi tiết một kết quả theo loại của nó (có kiểm tra quyền xem). */
    private function resolveItem(array $item, Request $request): JsonResponse
    {
        $type = $item['type'] ?? '';

        if ($type === 'imei') {
            if (! $this->allowed($request, 'products.view')) {
                return $this->noMatchResponse();
            }

            $imei = ProductImei::query()
                ->with(['product:id,name,sku,warranty_days', 'variant:id,product_id,sku,attributes'])
                ->find((int) ($item['id'] ?? 0));

            return $imei ? $this->selectionResponse($imei, null) : $this->noMatchResponse();
        }

        if ($type === 'repair') {
            if (! $this->allowed($request, 'repairs.view')) {
                return $this->noMatchResponse();
            }

            $repair = Repair::query()->find((int) ($item['repair_id'] ?? 0));

            if (! $repair) {
                return $this->noMatchResponse();
            }

            $code = (string) ($repair->imei ?: $repair->serial);

            return $this->selectionResponse($code !== '' ? $this->findImeiByCode($code) : null, $repair);
        }

        $permission = EntityLookupService::PERMISSIONS[$type] ?? null;

        if (! $permission || ! $this->allowed($request, $permission)) {
            return $this->noMatchResponse();
        }

        $profile = app(EntityLookupService::class)->profile($type, (int) ($item['id'] ?? 0), $item['filter_status'] ?? null);

        return $profile ? response()->json($profile) : $this->noMatchResponse();
    }

    /**
     * Gợi ý theo các phạm vi đã chọn (gộp kết quả, bỏ trùng):
     *  all · sold (đã bán) · in_stock (trong kho) · repair (đã/đang sửa)
     *  product · customer · user · supplier · sale (hóa đơn) · import (phiếu nhập)
     */
    private function scopeSuggestions(string $keyword, array $scopes, int $limit, Request $request): Collection
    {
        $share = in_array('all', $scopes, true) ? $limit : max(4, (int) ceil($limit / max(1, count($scopes))));

        $result = collect();

        foreach ($scopes as $scope) {
            $result = $result->concat($this->singleScope($keyword, $scope, $share, $request));
        }

        return $this->collapseDevices(
            $result
                ->unique(fn (array $item) => ($item['type'] ?? '') . '-' . ($item['id'] ?? $item['repair_id'] ?? ''))
                ->values()
        );
    }

    /**
     * Gom kết quả giống nhau: nhiều thiết bị IMEI cùng một sản phẩm (khớp theo tên máy / biến thể)
     * chỉ hiện MỘT dòng sản phẩm, kèm số lượng thiết bị. Thiết bị khớp theo chính IMEI/Serial,
     * khách hàng hoặc mã hóa đơn vẫn hiện riêng từng thiết bị.
     */
    private function collapseDevices(Collection $items): Collection
    {
        $byProduct = [];
        $merged = [];

        foreach ($items as $item) {
            $type = $item['type'] ?? '';
            $productId = (int) ($item['product_id'] ?? ($type === 'product' ? $item['id'] : 0));
            $collapsible = $type === 'product'
                || ($type === 'imei' && $productId > 0 && in_array($item['match'] ?? '', ['Thông tin máy', 'Thông tin thiết bị'], true));

            if (! $collapsible) {
                $merged[] = $item;

                continue;
            }

            if (! isset($byProduct[$productId])) {
                $byProduct[$productId] = count($merged);
                $merged[] = $type === 'product' ? $item : [
                    'type' => 'product',
                    'id' => $productId,
                    'title' => $item['product_name'] ?? 'Sản phẩm',
                    'lines' => array_values(array_filter([$item['sku'] ?? null])),
                    'badge' => 'Sản phẩm',
                    'tone' => 'indigo',
                    'match' => $item['match'] ?? '',
                    'filter_status' => $item['filter_status'] ?? null,
                    'placeholder' => true,
                ];

                continue;
            }

            $position = $byProduct[$productId];

            // Cùng một sản phẩm đã có: ưu tiên dòng sản phẩm đầy đủ (có tồn kho, giá) trong danh mục.
            if ($type === 'product' && ($merged[$position]['type'] ?? '') === 'product' && ($merged[$position]['placeholder'] ?? false)) {
                $merged[$position] = $item + ['filter_status' => $merged[$position]['filter_status'] ?? null];
            }

            // Xuất hiện ở cả "đã bán" và "trong kho" thì không lọc theo trạng thái nữa.
            if (($merged[$position]['filter_status'] ?? null) !== ($item['filter_status'] ?? null)) {
                $merged[$position]['filter_status'] = null;
            }
        }

        // Bổ sung số lượng thiết bị IMEI cho các dòng sản phẩm.
        $ids = collect($merged)->where('type', 'product')->pluck('id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect($merged);
        }

        $counts = ProductImei::query()
            ->selectRaw('product_id, status, COUNT(*) as total')
            ->whereIn('product_id', $ids)
            ->groupBy('product_id', 'status')
            ->get()
            ->groupBy('product_id');

        return collect($merged)->map(function (array $item) use ($counts): array {
            if (($item['type'] ?? '') !== 'product') {
                return $item;
            }

            $rows = $counts->get($item['id'], collect());
            $total = (int) $rows->sum('total');

            if ($total === 0) {
                return $item;
            }

            $inStock = (int) $rows->where('status', ProductImei::STATUS_IN_STOCK)->sum('total');
            $sold = (int) $rows->where('status', ProductImei::STATUS_SOLD)->sum('total');

            $item['imei_total'] = $total;
            $item['lines'] = array_values(array_merge(
                array_filter($item['lines'] ?? [], fn ($line) => ! str_starts_with((string) $line, 'Tồn:')),
                ["{$total} thiết bị ({$inStock} trong kho · {$sold} đã bán)"]
            ));

            return $item;
        })->values();
    }

    private function singleScope(string $keyword, string $scope, int $limit, Request $request): Collection
    {
        $entities = app(EntityLookupService::class);
        $canProducts = $this->allowed($request, 'products.view');
        $canRepairs = $this->allowed($request, 'repairs.view');

        if (isset(EntityLookupService::PERMISSIONS[$scope])) {
            return $this->allowed($request, EntityLookupService::PERMISSIONS[$scope])
                ? $entities->suggest($scope, $keyword, $limit)->values()
                : collect();
        }

        if ($scope === 'repair') {
            return $canRepairs
                ? $this->buildSuggestions(collect(), $this->searchRepairs($keyword, $limit), $keyword)
                : collect();
        }

        if ($scope === 'sold' || $scope === 'in_stock') {
            if (! $canProducts) {
                return collect();
            }

            $status = $scope === 'sold' ? ProductImei::STATUS_SOLD : ProductImei::STATUS_IN_STOCK;
            [$imeis] = $this->searchCandidates($keyword, $limit, 0, $status);

            // Kèm các sản phẩm không quản lý theo IMEI (màn hình, linh kiện...) đã bán / còn tồn.
            return $this->buildSuggestions($imeis, collect(), $keyword)
                ->concat($entities->suggestProducts($keyword, $limit, $scope))
                ->map(fn (array $item) => $item + ['filter_status' => $status]);
        }

        // all: thiết bị + phiếu sửa máy ngoài + sản phẩm + khách hàng + người dùng + nhà cung cấp + hóa đơn + phiếu nhập
        $each = max(5, intdiv($limit, 3));
        $result = collect();

        if ($canProducts || $canRepairs) {
            [$imeis, $repairOnly] = $this->searchCandidates($keyword, $canProducts ? $limit : 0, $canRepairs ? $each : 0);
            $result = $this->buildSuggestions($canProducts ? $imeis : collect(), $canRepairs ? $repairOnly : collect(), $keyword);
        }

        foreach (EntityLookupService::TYPES as $type) {
            if ($this->allowed($request, EntityLookupService::PERMISSIONS[$type])) {
                $result = $result->concat($entities->suggest($type, $keyword, $each));
            }
        }

        return $result->values();
    }

    private function searchRepairs(string $keyword, int $limit): Collection
    {
        return Repair::query()
            ->with('customer:id,full_name,phone')
            ->where($this->repairMatcher($keyword))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** Phiếu sửa khớp từ khóa: mọi từ đều phải xuất hiện ở mã phiếu, tên máy, IMEI, khách hoặc SĐT. */
    private function repairMatcher(string $keyword): \Closure
    {
        $tokens = EntityLookupService::tokens($keyword);

        // Bảng repairs không có cột tên/SĐT khách: khách nằm ở quan hệ customer, SĐT liên hệ ở contact_phone.
        return function ($query) use ($tokens): void {
            foreach ($tokens as $token) {
                $like = EntityLookupService::likeOf($token);
                $digits = preg_replace('/\D+/', '', $token) ?? '';
                $phoneLike = strlen($digits) >= 3 ? '%' . $digits . '%' : $like;

                $query->where(function ($w) use ($like, $phoneLike, $token): void {
                    $w->where('code', 'like', $like)
                        ->orWhere('device_name', 'like', $like)
                        ->orWhere('imei', 'like', $like)
                        ->orWhere('serial', 'like', $like)
                        ->orWhere('contact_phone', 'like', $phoneLike)
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->where('full_name', 'like', $like)
                            ->orWhere('search_text', 'like', EntityLookupService::likeOf(EntityLookupService::normalized($token)))
                            ->orWhere('code', 'like', $like)
                            ->orWhere('phone', 'like', $phoneLike));
                });
            }
        };
    }

    private function selectionResponse(?ProductImei $imei, ?Repair $repair): JsonResponse
    {
        $code = (string) ($imei?->imei ?: $imei?->serial ?: $repair?->imei ?: $repair?->serial ?: '');

        $payload = $this->lookupPayload(
            $imei,
            $code,
            $repair ? collect([$repair->id]) : null
        );

        $payload['repair_only'] = ! $imei;
        $payload['message'] = match (true) {
            ! $imei => 'Thiết bị không có trong kho, hiển thị phiếu sửa chữa liên quan.',
            $imei->status === ProductImei::STATUS_IN_STOCK => 'Máy đang còn trong kho.',
            $imei->status === ProductImei::STATUS_SOLD => 'Máy đã bán cho khách.',
            default => 'Máy đã rời kho.',
        };

        return response()->json($payload);
    }

    private function suggestions(Request $request): JsonResponse
    {
        $keyword = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $scopes = $this->lookupScopes($request);

        if (mb_strlen($keyword) < 2) {
            return response()->json(['query' => $keyword, 'scope' => $scopes, 'suggestions' => []]);
        }

        return response()->json([
            'query' => $keyword,
            'scope' => $scopes,
            'suggestions' => $this->scopeSuggestions($keyword, $scopes, in_array('all', $scopes, true) ? 12 : 8, $request),
        ]);
    }

    /**
     * Tìm theo: IMEI/Serial, khách hàng (tên, SĐT), mã hóa đơn, tên/SKU máy,
     * biến thể + ghi chú thiết bị, và phiếu sửa chữa (mã phiếu, tên máy, khách, SĐT).
     *
     * @return array{0: Collection<int, ProductImei>, 1: Collection<int, Repair>}
     */
    private function searchCandidates(string $keyword, int $imeiLimit, int $repairLimit, ?string $status = null): array
    {
        $tokens = EntityLookupService::tokens($keyword);

        $matchedRepairs = $repairLimit > 0 || $imeiLimit > 0
            ? Repair::query()
                ->with('customer:id,full_name,phone')
                ->where($this->repairMatcher($keyword))
                ->latest('id')
                ->limit(100)
                ->get()
            : collect();

        $repairCodes = $matchedRepairs
            ->pluck('imei')
            ->merge($matchedRepairs->pluck('serial'))
            ->filter()
            ->unique()
            ->values();

        $imeis = collect();

        if ($imeiLimit > 0) {
            $imeis = ProductImei::query()
                ->with([
                    'product:id,name,sku,warranty_days',
                    'variant:id,product_id,sku,attributes',
                    'saleItems' => fn ($query) => $query->latest('id'),
                    'saleItems.sale:id,code,customer_id,created_at',
                    'saleItems.sale.customer:id,full_name,phone',
                ])
                ->where(function ($query) use ($tokens, $repairCodes): void {
                    // Mọi từ khóa đều phải xuất hiện ở đâu đó (IMEI, máy, thiết bị, khách, hóa đơn...).
                    $query->where(function ($direct) use ($tokens): void {
                        foreach ($tokens as $token) {
                            $direct->where(fn ($w) => $this->imeiTokenMatch($w, $token));
                        }
                    });

                    if ($repairCodes->isNotEmpty()) {
                        $query
                            ->orWhereIn('imei', $repairCodes)
                            ->orWhereIn('serial', $repairCodes);
                    }
                })
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderByRaw('CASE WHEN imei = ? OR serial = ? THEN 0 ELSE 1 END', [$keyword, $keyword])
                ->latest('id')
                ->limit($imeiLimit)
                ->get();
        }

        // Phiếu sửa của máy không có trong kho (khách mang máy ngoài đến sửa).
        $knownCodes = $imeis->pluck('imei')->merge($imeis->pluck('serial'))->filter()->unique();
        $inventoryCodes = $repairCodes->isEmpty()
            ? collect()
            : ProductImei::query()
                ->whereIn('imei', $repairCodes)
                ->orWhereIn('serial', $repairCodes)
                ->get(['imei', 'serial'])
                ->flatMap(fn ($row) => [$row->imei, $row->serial])
                ->filter()
                ->unique();

        $repairOnly = $matchedRepairs
            ->filter(function (Repair $repair) use ($knownCodes, $inventoryCodes): bool {
                $codes = collect([$repair->imei, $repair->serial])->filter();

                return $codes->isEmpty()
                    || ($codes->intersect($knownCodes)->isEmpty() && $codes->intersect($inventoryCodes)->isEmpty());
            })
            ->take(max(0, $repairLimit))
            ->values();

        return [$imeis, $repairOnly];
    }

    /** Một từ khóa khớp với thiết bị ở bất kỳ thông tin nào: IMEI, máy, thiết bị, ghi chú, khách, hóa đơn. */
    private function imeiTokenMatch($w, string $token): void
    {
        $like = EntityLookupService::likeOf($token);
        $normLike = EntityLookupService::likeOf(EntityLookupService::normalized($token));
        $digits = preg_replace('/\D+/', '', $token) ?? '';
        $phoneLike = strlen($digits) >= 3 ? '%' . $digits . '%' : $like;

        $w->where('imei', 'like', $like)
            ->orWhere('serial', 'like', $like)
            ->orWhereHas('product', fn ($product) => $product
                ->where('name', 'like', $like)
                ->orWhere('search_text', 'like', $normLike)
                ->orWhere('sku', 'like', $like)
                ->orWhere('model', 'like', $like))
            ->orWhereHas('variant', fn ($variant) => $variant
                ->where('sku', 'like', $like)
                ->orWhere('attributes', 'like', $like))
            ->orWhere('extra_info', 'like', $like)
            ->orWhereHas('saleItems.sale', fn ($sale) => $sale
                ->where('code', 'like', $like)
                ->orWhereHas('customer', fn ($customer) => $customer
                    ->where('full_name', 'like', $like)
                    ->orWhere('search_text', 'like', $normLike)
                    ->orWhere('phone', 'like', $phoneLike)));
    }

    private function buildSuggestions(Collection $imeis, Collection $repairOnly, string $keyword): Collection
    {
        return $imeis
            ->map(function (ProductImei $imei) use ($keyword): array {
                $sale = $imei->saleItems->first()?->sale;
                $customer = $sale?->customer;
                $variant = $this->variantLabel($imei->variant);

                return [
                    'type' => 'imei',
                    'id' => $imei->id,
                    'product_id' => $imei->product_id,
                    'imei' => $imei->imei,
                    'serial' => $imei->serial,
                    'product_name' => $imei->product?->name,
                    'sku' => $imei->product?->sku,
                    'variant' => $variant,
                    'status' => $imei->status,
                    'customer_name' => $customer?->full_name,
                    'customer_phone' => $customer?->phone,
                    'sale_code' => $sale?->code,
                    'match' => $this->matchLabel($keyword, [
                        'IMEI / Serial' => [$imei->imei, $imei->serial],
                        'Khách hàng' => [$customer?->full_name, $customer?->phone],
                        'Mã hóa đơn' => [$sale?->code],
                        'Thông tin máy' => [$imei->product?->name, $imei->product?->sku],
                        'Thông tin thiết bị' => [$variant, $imei->variant?->sku, json_encode($imei->extra_info, JSON_UNESCAPED_UNICODE)],
                    ]),
                ];
            })
            ->concat($repairOnly->map(fn (Repair $repair): array => [
                'type' => 'repair',
                'repair_id' => $repair->id,
                'code' => $repair->code,
                'device_name' => $repair->device_name,
                'imei' => $repair->imei,
                'serial' => $repair->serial,
                'status' => $repair->status,
                'customer_name' => $repair->customer?->full_name,
                'customer_phone' => $repair->customer?->phone ?: $repair->contact_phone,
                'match' => $this->matchLabel($keyword, [
                    'Phiếu sửa chữa' => [$repair->code],
                    'Khách hàng' => [$repair->customer?->full_name, $repair->customer?->phone, $repair->contact_phone],
                    'Thông tin máy' => [$repair->device_name],
                    'IMEI / Serial' => [$repair->imei, $repair->serial],
                ]),
            ]))
            ->values();
    }

    /** Nhãn cho biết gợi ý khớp vì thông tin nào (không phân biệt hoa thường và dấu tiếng Việt). */
    private function matchLabel(string $keyword, array $groups): string
    {
        $needle = Str::lower(Str::ascii($keyword));
        $digits = preg_replace('/\D+/', '', $keyword) ?? '';

        foreach ($groups as $label => $values) {
            foreach ($values as $value) {
                $value = (string) $value;

                if ($value === '') {
                    continue;
                }

                if (str_contains(Str::lower(Str::ascii($value)), $needle)) {
                    return $label;
                }

                if (strlen($digits) >= 3 && str_contains(preg_replace('/\D+/', '', $value) ?? '', $digits)) {
                    return $label;
                }
            }
        }

        return 'Thông tin liên quan';
    }

    private function variantLabel($variant): ?string
    {
        if (! $variant) {
            return null;
        }

        $attributes = $variant->attributes;

        if (is_array($attributes) && $attributes !== []) {
            $text = collect($attributes)
                ->map(fn ($value) => is_array($value) ? ($value['value'] ?? $value['name'] ?? $value['label'] ?? '') : $value)
                ->filter()
                ->implode(' / ');

            if ($text !== '') {
                return $text;
            }
        }

        return $variant->sku;
    }

    private function warrantyInfo(?ProductImei $imei): array
    {
        if (! $imei) {
            return [
                'status' => 'not_found',
                'label' => 'Chưa có dữ liệu BH NCC',
                'start_at' => null,
                'expired_at' => null,
                'remaining_days' => null,
            ];
        }

        $startAt = $imei->imported_at
            ? Carbon::parse($imei->imported_at)
            : null;

        $expiredAt = $imei->warranty_expired_at
            ? Carbon::parse($imei->warranty_expired_at)
            : null;

        if (! $startAt && ! $expiredAt) {
            return [
                'status' => 'not_started',
                'label' => 'Chưa nhập hạn BH NCC',
                'start_at' => null,
                'expired_at' => null,
                'remaining_days' => null,
            ];
        }

        if (! $expiredAt) {
            return [
                'status' => 'unknown',
                'label' => 'Chưa xác định hạn BH NCC',
                'start_at' => $startAt,
                'expired_at' => null,
                'remaining_days' => null,
            ];
        }

        $remainingDays = now()->startOfDay()->diffInDays($expiredAt->copy()->startOfDay(), false);

        return [
            'status' => $remainingDays >= 0 ? 'active' : 'expired',
            'label' => $remainingDays >= 0 ? 'Còn BH NCC' : 'Hết BH NCC',
            'start_at' => $startAt,
            'expired_at' => $expiredAt,
            'remaining_days' => $remainingDays,
        ];
    }


    /** Bảo hành cửa hàng cấp cho khách (tính từ ngày bán). */
    private function customerWarrantyInfo(?ProductImei $imei, Collection $saleItems): array
    {
        $empty = ['start_at' => null, 'expired_at' => null, 'remaining_days' => null, 'days' => null, 'reason' => null];

        if (! $imei) {
            return ['status' => 'not_found', 'label' => 'Chưa có dữ liệu bảo hành'] + $empty;
        }

        $soldAt = $imei->sold_at ? Carbon::parse($imei->sold_at) : null;

        if ($imei->customer_warranty_voided_at) {
            return [
                'status' => 'voided',
                'label' => 'Bảo hành đã bị hủy',
                'start_at' => $soldAt,
                'expired_at' => $imei->customer_warranty_expires_at,
                'remaining_days' => null,
                'days' => $imei->customer_warranty_days,
                'reason' => $imei->customer_warranty_void_reason,
            ];
        }

        $expiredAt = $imei->customer_warranty_expires_at ? Carbon::parse($imei->customer_warranty_expires_at) : null;
        $days = (int) ($imei->customer_warranty_days ?: ($saleItems->first()?->warranty_days ?: $imei->product?->warranty_days));

        if (! $expiredAt && $soldAt && $days > 0) {
            $expiredAt = $soldAt->copy()->addDays($days);
        }

        if (! $soldAt && ! $expiredAt) {
            return ['status' => 'not_sold', 'label' => 'Chưa bán cho khách'] + $empty;
        }

        if (! $expiredAt) {
            return ['status' => 'none', 'label' => 'Không có bảo hành cửa hàng'] + array_merge($empty, ['start_at' => $soldAt]);
        }

        $remaining = now()->startOfDay()->diffInDays($expiredAt->copy()->startOfDay(), false);

        return [
            'status' => $remaining >= 0 ? 'active' : 'expired',
            'label' => $remaining >= 0 ? 'Còn bảo hành' : 'Hết bảo hành',
            'start_at' => $soldAt,
            'expired_at' => $expiredAt,
            'remaining_days' => $remaining,
            'days' => $days ?: null,
            'reason' => null,
        ];
    }

    /** Bảo hành sau sửa chữa: lấy phiếu có hạn bảo hành mới nhất. */
    private function repairWarrantyInfo(Collection $repairs): array
    {
        $empty = ['code' => null, 'start_at' => null, 'expired_at' => null, 'remaining_days' => null, 'days' => null, 'reason' => null];

        $repair = $repairs
            ->filter(fn (Repair $item) => $item->repair_warranty_expires_at || $item->repair_warranty_voided_at)
            ->sortByDesc(fn (Repair $item) => $item->repair_warranty_expires_at ?? $item->repair_warranty_voided_at)
            ->first();

        if (! $repair) {
            return ['status' => 'none', 'label' => 'Chưa có bảo hành sửa chữa'] + $empty;
        }

        $expiredAt = $repair->repair_warranty_expires_at ? Carbon::parse($repair->repair_warranty_expires_at) : null;

        if ($repair->repair_warranty_voided_at) {
            return [
                'status' => 'voided',
                'label' => 'Bảo hành sửa chữa đã bị hủy',
                'code' => $repair->code,
                'start_at' => $repair->repair_warranty_started_at,
                'expired_at' => $expiredAt,
                'remaining_days' => null,
                'days' => $repair->repair_warranty_days,
                'reason' => $repair->repair_warranty_void_reason,
            ];
        }

        $remaining = $expiredAt ? now()->startOfDay()->diffInDays($expiredAt->copy()->startOfDay(), false) : null;

        return [
            'status' => $remaining === null ? 'none' : ($remaining >= 0 ? 'active' : 'expired'),
            'label' => $remaining === null ? 'Chưa xác định hạn bảo hành' : ($remaining >= 0 ? 'Còn bảo hành sửa chữa' : 'Hết bảo hành sửa chữa'),
            'code' => $repair->code,
            'start_at' => $repair->repair_warranty_started_at,
            'expired_at' => $expiredAt,
            'remaining_days' => $remaining,
            'days' => $repair->repair_warranty_days,
            'reason' => null,
        ];
    }

    // Hiển thị chi tiết IMEI
    public function show(
        ProductImei $imei
    ): Response {

        $imei->load([

            'product',

            'histories' => fn ($query) => $query
                ->latest('happened_at')
                ->latest('id'),

            'saleItems.sale.customer',
        ]);

        $repairs = Repair::query()
            ->with('technician:id,name')
            ->where('imei', $imei->imei)
            ->latest('id')
            ->get();

        return Inertia::render(

            'ProductImeis/Show',

            [
                'imei' => $imei,
                'repairs' => $repairs,
            ]
        );
    }

}
