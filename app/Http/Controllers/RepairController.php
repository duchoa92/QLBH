<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RepairRequest;
use App\Models\Repair;
use App\Models\RepairImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;
use App\Models\RepairTimeline;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerDebt;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductVariant;
use App\Models\RepairPart;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;

class RepairController extends Controller
{
    /**
     * Danh sách phiếu sửa.
     */
    public function index(
        Request $request
        ): Response {

        $search = trim(
            (string) $request->input(
                'search'
            )
        );

        $status = trim(
            (string) $request->input(
                'status'
            )
        );

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $sortBy = in_array($request->input('sort_by'), ['code', 'device_name', 'status', 'created_at'], true)
            ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $createdRepairId = (int) $request->query('created_repair_id', 0);
        $statusCounts = Repair::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereIn('status', ['pending', 'repairing', 'done'])
            ->groupBy('status')
            ->pluck('total', 'status');

        $repairs = Repair::query()

            ->with(['customer', 'images', 'technician', 'timelines.user', 'timelines.images', 'parts'])

            ->when(

                $search !== '',

                function ($query) use ($search): void {

                    $query->where(

                        function ($q) use ($search): void {

                            $q

                                ->where(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'imei',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'device_name',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhereHas(

                                    'customer',

                                    function ($customerQuery)
                                    use ($search): void {

                                        $customerQuery

                                            ->where(
                                                'full_name',
                                                'like',
                                                "%{$search}%"
                                            )

                                            ->orWhere(
                                                'phone',
                                                'like',
                                                "%{$search}%"
                                            )

                                            ->orWhere(
                                                'cccd',
                                                'like',
                                                "%{$search}%"
                                            );
                                    }
                                );
                        }
                    );
                }
            )

            ->when(

                $status !== '',

                function ($query) use ($status): void {

                    $query->where(
                        'status',
                        $status
                    );
                }
            )

            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($sortBy, $sortOrder)

            ->paginate(20)

            ->through(fn ($repair) => [

                'id' => $repair->id,

                'code' => $repair->code,

                'customer' => [

                    'id' =>
                        $repair->customer?->id,

                    'name' =>
                        $repair->customer?->full_name,

                    'phone' =>
                        $repair->customer?->phone,

                    'identity_card' =>
                        $repair->customer?->cccd,
                    'debt_balance' => (float) ($repair->customer?->debt_balance ?? 0),
                ],

                'device_name' =>
                    $repair->device_name,

                'intake_type' => $repair->intake_type ?: 'repair',
                'warranty_source_type' => $repair->warranty_source_type,
                'warranty_source_id' => $repair->warranty_source_id,
                'warranty_expires_at' => $repair->warranty_expires_at,
                'warranty_status' => $repair->warranty_status,
                'warranty_decline_reason' => $repair->warranty_decline_reason,
                'warranty_covered_amount' => (float) ($repair->warranty_covered_amount ?? 0),
                'repair_warranty_days' => (int) ($repair->repair_warranty_days ?? 0),
                'repair_warranty_expires_at' => $repair->repair_warranty_expires_at,
                'repair_warranty_voided_at' => $repair->repair_warranty_voided_at,
                'repair_warranty_void_reason' => $repair->repair_warranty_void_reason,

                'contact_phone' => $repair->contact_phone,

                'screen_password' => $repair->screen_password,

                'screen_pattern' => $repair->screen_pattern,

                'serial' => $repair->serial,

                'account_type' => $repair->account_type,

                'account_email' => $repair->account_email,

                'account_password' => $repair->account_password,

                'repair_request' => $repair->repair_request,

                'issue' => $repair->issue,

                'estimated_cost' => $repair->estimated_cost,

                'final_cost' => $repair->final_cost,
                'parts_total' => $repair->parts_total,
                'labor_cost' => $repair->labor_cost,
                'surcharge' => $repair->surcharge,
                'paid_amount' => $repair->paid_amount,
                'change_amount' => $repair->change_amount,
                'payment_method' => $repair->payment_method,
                'payment_note' => $repair->payment_note,
                'parts' => $repair->parts->map(fn ($part) => [
                    'id' => $part->id,
                    'product_name' => $part->product_name,
                    'sku' => $part->sku,
                    'quantity' => $part->quantity,
                    'unit_price' => (float) $part->unit_price,
                    'subtotal' => (float) $part->subtotal,
                ]),

                'note' => $repair->note,

                'accessories' => $repair->accessories,

                'technician' => $repair->technician?->name,

                'images' => $repair->images->whereNull('timeline_id')->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => url('storage/' . $image->image_path),
                ]),

                'timelines' => $repair->timelines->map(fn ($timeline) => [
                    'id' => $timeline->id,
                    'title' => $timeline->title,
                    'description' => $timeline->description,
                    'status' => $timeline->status,
                    'issue' => $timeline->issue,
                    'parts_needed' => $timeline->parts_needed,
                    'waiting_for_parts' => (bool) $timeline->waiting_for_parts,
                    'waiting_mode' => $timeline->waiting_mode ?? ($timeline->waiting_for_parts ? 'parts' : 'repair_now'),
                    'expected_days' => $timeline->expected_days,
                    'images' => $timeline->images->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => url('storage/' . $image->image_path),
                    ]),
                    'created_at' => $timeline->created_at?->format('d/m/Y H:i'),
                    'user' => $timeline->user?->name,
                ]),
                'waiting_for_parts' => (bool) ($repair->timelines->first()?->waiting_for_parts ?? false),
                'waiting_mode' => $repair->timelines->first()?->waiting_mode ?? ($repair->timelines->first()?->waiting_for_parts ? 'parts' : 'repair_now'),
                'parts_needed' => $repair->timelines->first()?->parts_needed,
                'expected_days' => $repair->timelines->first()?->expected_days,

                'imei' =>
                    $repair->imei,

                'status' =>
                    $repair->status,

                'created_at' =>
                    $repair->created_at?->format(
                        'd/m/Y H:i'
                    ),
            ])

            ->withQueryString();

        return inertia(

            'Repairs/Index',

            [

                'repairs' => $repairs,
                'createdRepairId' => $createdRepairId,
                'stats' => [
                    'pending_count' => (int) ($statusCounts['pending'] ?? 0),
                    'repairing_count' => (int) ($statusCounts['repairing'] ?? 0),
                    'done_count' => (int) ($statusCounts['done'] ?? 0),
                ],

                'filters' => [

                    'search' => $search,

                    'status' => $status,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ]
        );

        }

    /**
     * Gợi ý dữ liệu.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $issues = collect();
        $accessories = collect();
        if ($keyword === '') {
            $issues = Repair::query()
                ->whereNotNull('issue')
                ->pluck('issue')
                ->flatten()
                ->filter()
                ->unique()
                ->values();

            $accessories = Repair::query()
                ->whereNotNull('accessories')
                ->pluck('accessories')
                ->flatten()
                ->filter()
                ->unique()
                ->values();
        }
        $devices = collect();
        $imeis = collect();
        $customers = collect();

        if (mb_strlen($keyword) >= 2) {
            $like = '%' . $keyword . '%';

            $customers = Customer::query()
                ->where(fn ($query) => $query
                    ->where('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('cccd', 'like', $like)
                    ->orWhere('code', 'like', $like))
                ->orderBy('full_name')
                ->limit(8)
                ->get(['id', 'code', 'full_name', 'phone', 'cccd', 'debt_balance']);

            $devices = Repair::query()
                ->where(fn ($query) => $query->where('device_name', 'like', $like)->orWhere('imei', 'like', $like))
                ->latest('id')
                ->limit(10)
                ->get(['device_name', 'imei'])
                ->map(fn (Repair $repair): array => [
                    'name' => $repair->device_name,
                    'imei' => $repair->imei,
                    'source' => 'Đã từng sửa',
                ]);

            $customerDevices = CustomerDevice::query()
                ->where(fn ($query) => $query->where('model', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('imei', 'like', $like)->orWhere('serial', 'like', $like))
                ->latest('id')
                ->limit(10)
                ->get(['brand', 'model', 'imei', 'serial'])
                ->map(fn (CustomerDevice $device): array => [
                    'name' => trim(implode(' ', array_filter([$device->brand, $device->model]))) ?: 'Thiết bị khách hàng',
                    'imei' => $device->imei ?: $device->serial,
                    'source' => 'Thiết bị khách hàng',
                ]);

            $soldProducts = Product::query()
                ->where('name', 'like', $like)
                ->where(fn ($query) => $query->whereHas('imeis')->orWhereExists(
                    fn ($sales) => $sales->selectRaw('1')->from('sale_items')->whereColumn('sale_items.product_id', 'products.id')
                ))
                ->orderBy('name')
                ->limit(10)
                ->get(['name'])
                ->map(fn (Product $product): array => ['name' => $product->name, 'imei' => null, 'source' => 'Đã mua bán']);

            $devices = $devices->concat($customerDevices)->concat($soldProducts)
                ->filter(fn (array $item) => filled($item['name']))
                ->unique(fn (array $item) => mb_strtolower($item['name']))
                ->take(10)
                ->values();

            $repairImeis = Repair::query()
                ->with('customer:id,full_name,phone,cccd,debt_balance')
                ->where(fn ($query) => $query->where('device_name', 'like', $like)->orWhere('imei', 'like', $like)->orWhere('serial', 'like', $like))
                ->latest('id')
                ->limit(10)
                ->get([
                    'id', 'customer_id', 'device_name', 'imei', 'serial', 'screen_password', 'screen_pattern',
                    'account_type', 'account_email', 'account_password', 'issue', 'repair_request', 'accessories',
                    'estimated_cost', 'note', 'created_at', 'repair_warranty_expires_at',
                    'repair_warranty_voided_at', 'repair_warranty_void_reason',
                ])
                ->map(fn (Repair $repair): array => [
                    'imei' => $repair->imei ?: $repair->serial,
                    'name' => $repair->device_name,
                    'source' => 'Đã sửa · ' . $repair->created_at?->format('d/m/Y'),
                    'source_date' => $repair->created_at?->toAtomString(),
                    'customer' => $repair->customer ? [
                        'id' => $repair->customer->id,
                        'full_name' => $repair->customer->full_name,
                        'phone' => $repair->customer->phone,
                        'cccd' => $repair->customer->cccd,
                        'debt_balance' => (float) $repair->customer->debt_balance,
                    ] : null,
                    'device_data' => [
                        'screen_password' => $repair->screen_password,
                        'screen_pattern' => $repair->screen_pattern,
                        'account_type' => $repair->account_type,
                        'account_email' => $repair->account_email,
                        'account_password' => $repair->account_password,
                        'issue' => $repair->issue ?? [],
                        'repair_request' => $repair->repair_request,
                        'accessories' => $repair->accessories ?? [],
                        'estimated_cost' => $repair->estimated_cost,
                        'note' => $repair->note,
                    ],
                    'warranty' => $repair->repair_warranty_expires_at ? [
                        'source_type' => 'repair',
                        'source_id' => $repair->id,
                        'source_customer_id' => $repair->customer_id,
                        'expires_at' => $repair->repair_warranty_expires_at->toAtomString(),
                        'voided_at' => $repair->repair_warranty_voided_at?->toAtomString(),
                        'void_reason' => $repair->repair_warranty_void_reason,
                        'active' => ! $repair->repair_warranty_voided_at && $repair->repair_warranty_expires_at->endOfDay()->gte(now()),
                    ] : null,
                ]);

            $customerImeis = CustomerDevice::query()
                ->with('customer:id,full_name,phone,cccd,debt_balance')
                ->where(fn ($query) => $query->where('brand', 'like', $like)->orWhere('model', 'like', $like)->orWhere('imei', 'like', $like)->orWhere('serial', 'like', $like))
                ->latest('id')
                ->limit(10)
                ->get(['id', 'customer_id', 'model', 'brand', 'imei', 'serial', 'created_at'])
                ->map(fn (CustomerDevice $device): array => [
                    'imei' => $device->imei ?: $device->serial,
                    'name' => trim(implode(' ', array_filter([$device->brand, $device->model]))) ?: null,
                    'source' => 'Thiết bị khách hàng',
                    'source_date' => $device->created_at?->toAtomString(),
                    'customer' => $device->customer ? [
                        'id' => $device->customer->id,
                        'full_name' => $device->customer->full_name,
                        'phone' => $device->customer->phone,
                        'cccd' => $device->customer->cccd,
                        'debt_balance' => (float) $device->customer->debt_balance,
                    ] : null,
                ]);

            $productImeis = ProductImei::query()
                ->with('product:id,name,warranty_days')
                ->where(fn ($query) => $query->where('imei', 'like', $like)->orWhere('serial', 'like', $like)->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', $like)))
                ->latest('id')
                ->limit(10)
                ->get([
                    'id', 'imei', 'serial', 'product_id', 'status', 'sold_at', 'customer_warranty_days',
                    'customer_warranty_expires_at', 'customer_warranty_voided_at', 'customer_warranty_void_reason',
                ])
                ->map(function (ProductImei $item): array {
                    $saleItems = $item->saleItems()
                        ->with('sale:id,code,customer_id,created_at,status', 'sale.customer:id,full_name,phone,cccd,debt_balance')
                        ->whereHas('sale', fn ($query) => $query->where('status', '!=', 'cancelled'))
                        ->latest('id')
                        ->get();
                    $saleItem = $saleItems->first();
                    $soldAt = $item->sold_at
                        ? Carbon::parse($item->sold_at)
                        : ($saleItem?->sale?->created_at ? Carbon::parse($saleItem->sale->created_at) : null);
                    $days = $item->customer_warranty_days;
                    $expiresAt = $days !== null
                        ? ($item->customer_warranty_expires_at
                            ? Carbon::parse($item->customer_warranty_expires_at)
                            : ((int) $days > 0 && $soldAt ? $soldAt->copy()->addDays((int) $days) : null))
                        : ($item->customer_warranty_expires_at
                            ? Carbon::parse($item->customer_warranty_expires_at)
                            : ((int) ($item->product?->warranty_days ?? 0) > 0 && $soldAt
                                ? $soldAt->copy()->addDays((int) $item->product->warranty_days)
                                : null));
                    $purchasers = $saleItems
                        ->filter(fn ($row) => $row->sale?->customer)
                        ->map(function ($row) use ($saleItem, $item, $expiresAt): array {
                            $purchasedAt = $row->sale?->created_at ? Carbon::parse($row->sale->created_at) : null;
                            $isLatestSale = $saleItem && (int) $row->id === (int) $saleItem->id;
                            $purchaserWarrantyDays = $isLatestSale
                                ? (int) ($item->customer_warranty_days ?? $item->product?->warranty_days ?? 0)
                                : (int) ($item->product?->warranty_days ?? 0);
                            $purchaserExpiresAt = $isLatestSale
                                ? $expiresAt
                                : ($purchaserWarrantyDays > 0 && $purchasedAt
                                    ? $purchasedAt->copy()->addDays($purchaserWarrantyDays)
                                    : null);
                            $purchaserWarrantyVoidedAt = $isLatestSale
                                ? $item->customer_warranty_voided_at
                                : null;

                            return [
                                'id' => $row->sale->customer->id,
                                'full_name' => $row->sale->customer->full_name,
                                'phone' => $row->sale->customer->phone,
                                'cccd' => $row->sale->customer->cccd,
                                'debt_balance' => (float) $row->sale->customer->debt_balance,
                                'invoice_code' => $row->sale->code,
                                'purchased_at' => $purchasedAt?->toAtomString(),
                                'warranty_expires_at' => $purchaserExpiresAt?->toAtomString(),
                                'warranty_active' => $purchaserExpiresAt
                                    && ! $purchaserWarrantyVoidedAt
                                    && $purchaserExpiresAt->endOfDay()->gte(now()),
                                'warranty_voided_at' => $purchaserWarrantyVoidedAt?->toAtomString(),
                                'sale_item_id' => $row->id,
                            ];
                        })
                        ->unique(fn (array $purchaser) => $purchaser['sale_item_id'])
                        ->values();

                    return [
                        'imei' => $item->imei ?: $item->serial,
                        'name' => $item->product?->name,
                        'source' => $item->status === ProductImei::STATUS_IN_STOCK ? 'Còn trong kho' : 'Đã mua bán',
                        'inventory_status' => $item->status,
                        'in_stock' => $item->status === ProductImei::STATUS_IN_STOCK,
                        'source_date' => $soldAt?->toAtomString(),
                        'purchase_info' => $saleItem?->sale
                            ? 'Hóa đơn ' . $saleItem->sale->code . ' · ' . Carbon::parse($saleItem->sale->created_at)->format('d/m/Y')
                            : null,
                        'customer' => $saleItem?->sale?->customer ? [
                            'id' => $saleItem->sale->customer->id,
                            'full_name' => $saleItem->sale->customer->full_name,
                            'phone' => $saleItem->sale->customer->phone,
                            'cccd' => $saleItem->sale->customer->cccd,
                            'debt_balance' => (float) $saleItem->sale->customer->debt_balance,
                        ] : null,
                        'purchasers' => $purchasers,
                        'warranty' => ($expiresAt || $item->customer_warranty_voided_at) ? [
                            'source_type' => $saleItem ? 'sale_item' : null,
                            'source_id' => $saleItem?->id,
                            'sale_id' => $saleItem?->sale?->id,
                            'sale_customer_id' => $saleItem?->sale?->customer_id,
                            'invoice_code' => $saleItem?->sale?->code,
                            'expires_at' => $expiresAt?->toAtomString(),
                            'voided_at' => $item->customer_warranty_voided_at?->toAtomString(),
                            'void_reason' => $item->customer_warranty_void_reason,
                            'active' => ! $item->customer_warranty_voided_at && $expiresAt?->endOfDay()->gte(now()),
                        ] : null,
                    ];
                });

            $imeis = $repairImeis->concat($customerImeis)->concat($productImeis)
                ->filter(fn (array $item) => filled($item['imei']))
                ->groupBy(fn (array $item) => mb_strtolower(trim((string) $item['imei'])))
                ->map(function ($matches): array {
                    $withWarranty = $matches
                        ->filter(fn (array $item) => filled($item['warranty'] ?? null))
                        ->sortByDesc(fn (array $item) => ($item['warranty']['active'] ?? false)
                            ? 3
                            : (filled($item['warranty']['voided_at'] ?? null) ? 2 : 1))
                        ->first();
                    $item = $withWarranty ?: $matches->first();
                    if ($withWarranty) {
                        $item['warranty'] = $withWarranty['warranty'];
                    }
                    $withCustomer = $matches->first(fn (array $match) => ! empty($match['customer']));
                    $withDeviceData = $matches->first(fn (array $match) => ! empty($match['device_data']));
                    $withDate = $matches->first(fn (array $match) => ! empty($match['source_date']));
                    $withPurchase = $matches->first(fn (array $match) => ! empty($match['purchase_info']));
                    $inventoryMatch = $matches->first(fn (array $match) => array_key_exists('inventory_status', $match));
                    $purchasers = $matches
                        ->flatMap(fn (array $match) => $match['purchasers'] ?? [])
                        ->unique(fn (array $purchaser) => $purchaser['sale_item_id'] ?? $purchaser['id'])
                        ->values();
                    if (! empty($withWarranty['customer'] ?? null)) {
                        $item['customer'] = $withWarranty['customer'];
                    } elseif ($withCustomer) {
                        $item['customer'] = $withCustomer['customer'];
                    }
                    if ($withDeviceData) $item['device_data'] = $withDeviceData['device_data'];
                    if (empty($item['source_date']) && $withDate) $item['source_date'] = $withDate['source_date'];
                    if ($withPurchase) $item['purchase_info'] = $withPurchase['purchase_info'];
                    if ($inventoryMatch) {
                        $item['inventory_status'] = $inventoryMatch['inventory_status'];
                        $item['in_stock'] = (bool) ($inventoryMatch['in_stock'] ?? false);
                        if ($item['in_stock']) $item['source'] = 'Còn trong kho';
                    }
                    $item['purchasers'] = $purchasers;
                    $uniquePurchaserCustomers = $purchasers->unique('id')->values();
                    if ($uniquePurchaserCustomers->count() === 1) {
                        $item['customer'] = $uniquePurchaserCustomers->first();
                    } elseif ($uniquePurchaserCustomers->count() > 1) {
                        // Do not auto-select an arbitrary buyer when an IMEI has multiple purchase owners.
                        $item['customer'] = null;
                    }
                    return $item;
                })
                ->take(10)
                ->values();

            $excludedRepairId = (int) $request->query('exclude_repair_id', 0);
            $activeRepairsByImei = Repair::query()
                ->whereIn('status', ['pending', 'repairing'])
                ->where(fn ($query) => $query
                    ->where('device_name', 'like', $like)
                    ->orWhere('imei', 'like', $like)
                    ->orWhere('serial', 'like', $like))
                ->when($excludedRepairId > 0, fn ($query) => $query->where('id', '!=', $excludedRepairId))
                ->get(['id', 'code', 'status', 'imei', 'serial'])
                ->flatMap(function (Repair $repair): array {
                    $activeRepair = [
                        'id' => $repair->id,
                        'code' => $repair->code,
                        'status' => $repair->status,
                    ];
                    return collect([$repair->imei, $repair->serial])
                        ->filter(fn ($identifier) => filled($identifier))
                        ->mapWithKeys(fn ($identifier) => [mb_strtolower(trim((string) $identifier)) => $activeRepair])
                        ->all();
                })
                ->all();
            $activeRepairsByImei = collect($activeRepairsByImei);

            $attachActiveRepair = function (array $item) use ($activeRepairsByImei): array {
                $identifier = mb_strtolower(trim((string) ($item['imei'] ?? '')));
                if ($identifier !== '' && $activeRepairsByImei->has($identifier)) {
                    $item['active_repair'] = $activeRepairsByImei->get($identifier);
                }
                return $item;
            };
            $devices = $devices->map($attachActiveRepair);
            $imeis = $imeis->map($attachActiveRepair);
        }

        return response()->json([
            'issues' => $issues,
            'accessories' => $accessories,
            'devices' => $devices,
            'imeis' => $imeis,
            'customers' => $customers,
        ]);
    }

    /** Lịch sử thiết bị khách đã sửa chữa hoặc mua hàng. */
    public function customerDevices(Customer $customer): JsonResponse
    {
        $repairs = Repair::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(30)
            ->get([
                'id', 'device_name', 'imei', 'serial', 'screen_password', 'screen_pattern',
                'account_type', 'account_email', 'account_password', 'issue', 'repair_request',
                'accessories', 'estimated_cost', 'note', 'repair_warranty_started_at',
                'repair_warranty_expires_at', 'repair_warranty_voided_at', 'repair_warranty_void_reason',
            ])
            ->map(fn (Repair $repair): array => [
                'key' => 'repair-' . $repair->id,
                'device_name' => $repair->device_name,
                'imei' => $repair->imei,
                'serial' => $repair->serial,
                'screen_password' => $repair->screen_password,
                'screen_pattern' => $repair->screen_pattern,
                'account_type' => $repair->account_type,
                'account_email' => $repair->account_email,
                'account_password' => $repair->account_password,
                'issue' => $repair->issue ?? [],
                'repair_request' => $repair->repair_request,
                'accessories' => $repair->accessories ?? [],
                'estimated_cost' => $repair->estimated_cost,
                'note' => $repair->note,
                'source' => 'Đã sửa · ' . $repair->created_at?->format('d/m/Y'),
                'source_date' => $repair->created_at?->toAtomString(),
                'warranty' => $repair->repair_warranty_expires_at ? [
                    'source_type' => 'repair',
                    'source_id' => $repair->id,
                    'started_at' => $repair->repair_warranty_started_at?->toAtomString(),
                    'expires_at' => $repair->repair_warranty_expires_at->toAtomString(),
                    'voided_at' => $repair->repair_warranty_voided_at?->toAtomString(),
                    'void_reason' => $repair->repair_warranty_void_reason,
                    'active' => ! $repair->repair_warranty_voided_at && $repair->repair_warranty_expires_at->endOfDay()->gte(now()),
                ] : null,
            ]);

        $savedDevices = CustomerDevice::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(30)
            ->get(['id', 'brand', 'model', 'imei', 'serial'])
            ->map(fn (CustomerDevice $device): array => [
                'key' => 'customer-device-' . $device->id,
                'device_name' => trim(implode(' ', array_filter([$device->brand, $device->model]))) ?: 'Thiết bị khách hàng',
                'imei' => $device->imei,
                'serial' => $device->serial,
                'screen_password' => null,
                'screen_pattern' => null,
                'account_type' => null,
                'account_email' => null,
                'account_password' => null,
                'issue' => [],
                'repair_request' => null,
                'accessories' => [],
                'estimated_cost' => null,
                'note' => null,
                'source' => 'Thiết bị đã lưu',
                'warranty' => null,
            ]);

        $latestImeiSales = DB::table('sale_items as latest_imei_items')
            ->join('sales as latest_imei_sales', 'latest_imei_sales.id', '=', 'latest_imei_items.sale_id')
            ->where('latest_imei_sales.status', '!=', 'cancelled')
            ->whereNotNull('latest_imei_items.product_imei_id')
            ->groupBy('latest_imei_items.product_imei_id')
            ->selectRaw('latest_imei_items.product_imei_id, MAX(latest_imei_items.id) as latest_sale_item_id');

        $purchases = DB::table('sales')
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('product_imeis', 'product_imeis.id', '=', 'sale_items.product_imei_id')
            ->leftJoinSub($latestImeiSales, 'latest_imei_sale', fn ($join) => $join->on('latest_imei_sale.product_imei_id', '=', 'sale_items.product_imei_id'))
            ->where('sales.customer_id', $customer->id)
            ->where('sales.status', '!=', 'cancelled')
            ->orderByDesc('sales.created_at')
            ->limit(30)
            ->get([
                'sale_items.id', 'sale_items.product_imei_id', 'sales.id as sale_id', 'sales.code as invoice_code',
                'sales.created_at as purchased_at', 'products.name as device_name', 'products.warranty_days',
                'latest_imei_sale.latest_sale_item_id',
                'product_imeis.imei', 'product_imeis.serial', 'product_imeis.customer_warranty_expires_at',
                'product_imeis.customer_warranty_days',
                'product_imeis.customer_warranty_voided_at', 'product_imeis.customer_warranty_void_reason',
            ])
            ->map(function (object $item): array {
                $isLatestSale = $item->product_imei_id && (int) $item->id === (int) $item->latest_sale_item_id;
                $expiresAt = $isLatestSale && $item->customer_warranty_days !== null
                    ? ($item->customer_warranty_expires_at
                        ? Carbon::parse($item->customer_warranty_expires_at)
                        : ((int) $item->customer_warranty_days > 0
                            ? Carbon::parse($item->purchased_at)->addDays((int) $item->customer_warranty_days)
                            : null))
                    : ($isLatestSale && $item->customer_warranty_expires_at
                        ? Carbon::parse($item->customer_warranty_expires_at)
                        : ((int) $item->warranty_days > 0
                            ? Carbon::parse($item->purchased_at)->addDays((int) $item->warranty_days)
                            : null));
                $warrantyVoidedAt = $isLatestSale && $item->customer_warranty_voided_at
                    ? Carbon::parse($item->customer_warranty_voided_at)
                    : null;

                return [
                'key' => 'purchase-' . $item->id,
                'device_name' => $item->device_name,
                'imei' => $item->imei,
                'serial' => $item->serial,
                'screen_password' => null,
                'screen_pattern' => null,
                'account_type' => null,
                'account_email' => null,
                'account_password' => null,
                'issue' => [],
                'repair_request' => null,
                'accessories' => [],
                'estimated_cost' => null,
                'note' => null,
                'source' => 'Đã mua · ' . $item->invoice_code . ' · ' . Carbon::parse($item->purchased_at)->format('d/m/Y'),
                'source_date' => Carbon::parse($item->purchased_at)->toAtomString(),
                'warranty' => $item->product_imei_id && $expiresAt ? [
                    'source_type' => 'sale_item',
                    'source_id' => $item->id,
                    'sale_id' => $item->sale_id,
                    'invoice_code' => $item->invoice_code,
                    'started_at' => Carbon::parse($item->purchased_at)->toAtomString(),
                    'expires_at' => $expiresAt->toAtomString(),
                    'voided_at' => $warrantyVoidedAt?->toAtomString(),
                    'void_reason' => $isLatestSale ? $item->customer_warranty_void_reason : null,
                    'active' => ! $warrantyVoidedAt && $expiresAt->endOfDay()->gte(now()),
                ] : null,
                ];
            });

        $devices = $repairs->concat($savedDevices)->concat($purchases)
            ->filter(fn (array $device) => filled($device['device_name']))
            ->values();

        return response()->json(['devices' => $devices]);
    }

    /**
     * Lưu phiếu sửa.
     */
    public function store(
        RepairRequest $request
    ): RedirectResponse {

        DB::beginTransaction();

        try {

            $this->ensureImeiHasNoOpenRepair($request->input('imei'));

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */

            $customer = $request->customer_id
                ? Customer::query()->findOrFail($request->customer_id)
                : ($request->filled('customer_phone')
                    ? Customer::query()->where('phone', $request->customer_phone)->first()
                    : null);

            if (!$customer) {
                $customer = app(\App\Services\CustomerService::class)->create([
                    'full_name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'cccd' => $request->identity_card,
                ]);
            } else {
                // Keep the selected customer's editable intake fields in sync with the customer record.
                $customer->fill([
                    'full_name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'cccd' => $request->identity_card,
                ])->save();
            }

            $hasWarrantySource = $request->filled('warranty_source_type') && $request->filled('warranty_source_id');
            $warranty = $hasWarrantySource
                ? $this->resolveWarrantySource(
                    $customer,
                    (string) $request->input('warranty_source_type'),
                    (int) $request->input('warranty_source_id'),
                    (string) $request->input('imei', ''),
                )
                : null;

            /*
            |--------------------------------------------------------------------------
            | Repair
            |--------------------------------------------------------------------------
            */

            $repair = Repair::query()

                ->create([

                    'code' =>
                        'SC-' . now()->format('YmdHis'),

                    'customer_id' =>
                        $customer->id,

                    'contact_phone' =>
                        $request->contact_phone,

                    'device_name' =>
                        $request->device_name,

                    'imei' =>
                        $request->imei,

                    'screen_password' =>
                        $request->screen_password,

                    'screen_pattern' =>
                        $request->screen_pattern,

                    'account_type' =>
                        $request->account_type,

                    'account_email' =>
                        $request->account_email,

                    'account_password' =>
                        $request->account_password,

                    'issue' =>
                        $request->issue,

                    'repair_request' =>
                        $request->repair_request,

                    'accessories' =>
                        $request->accessories,

                    'estimated_cost' =>
                        $request->estimated_cost,

                    'note' =>
                        $request->note,

                    'status' =>
                        'pending',

                    'intake_type' => 'repair',
                    'warranty_source_type' => $warranty['source_type'] ?? null,
                    'warranty_source_id' => $warranty['source_id'] ?? null,
                    'warranty_expires_at' => $warranty['expires_at'] ?? null,
                    'warranty_status' => $warranty ? 'eligible' : null,

                    'received_at' =>
                        now(),
                ]);

            $deviceLookup = ['customer_id' => $customer->id];
            if ($request->filled('imei')) {
                $deviceLookup['imei'] = $request->imei;
            } else {
                $deviceLookup['model'] = $request->device_name;
            }
            CustomerDevice::query()->updateOrCreate($deviceLookup, [
                'model' => $request->device_name,
                'serial' => $request->imei,
            ]);





            /*
            |--------------------------------------------------------------------------
            | Tạo timeline
            |--------------------------------------------------------------------------
            */

            RepairTimeline::create([

                'repair_id' =>
                    $repair->id,

                'user_id' =>
                    auth()->id(),

                'status' => 'pending',

                'title' => 'Đã tiếp nhận máy',

                'description' => 'Tạo phiếu sửa chữa mới'
                    . ($warranty ? ' · Có căn cứ bảo hành theo ' . $warranty['reference'] . ', hạn đến ' . Carbon::parse($warranty['expires_at'])->format('d/m/Y') : ''),
            ]);




            /*
            |--------------------------------------------------------------------------
            | Upload ảnh
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('images')) {

                foreach (
                    $request->file('images')
                    as $image
                ) {

                    $path = $image->store(
                        'repairs',
                        'public'
                    );

                    RepairImage::query()
                        ->create([

                            'repair_id' =>
                                $repair->id,

                            'image_path' =>
                                $path,
                        ]);
                }
            }

            DB::commit();

            $this->notifyRepairEvent(
                $repair,
                'Tiếp nhận máy sửa chữa mới',
                ($customer->full_name ?: $customer->phone) . ' vừa gửi tiếp nhận ' . $repair->device_name . ' (' . $repair->code . ').',
                'repair'
            );

            return redirect()
                ->route('repairs.index', ['created_repair_id' => $repair->id])
                ->with('createdRepairId', $repair->id);

        } catch (\Throwable $e) {

            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Cập nhật trạng thái sửa chữa.
     */
    public function updateStatus(
        Request $request,
        Repair $repair
    ): RedirectResponse {

        $request->validate([
            'status' => ['sometimes', 'required', 'in:repairing,cancelled'],
            'description' => ['nullable', 'string', 'max:1000'],
            'issue' => ['nullable', 'array'],
            'issue.*' => ['string', 'max:255'],
            'waiting_for_parts' => ['nullable', 'boolean'],
            'waiting_mode' => ['nullable', 'in:repair_now,parts,wait_repair'],
            'parts_needed' => ['required_if:waiting_mode,parts', 'nullable', 'string', 'max:1000'],
            'expected_days' => ['required_if:waiting_mode,parts', 'nullable', 'integer', 'min:0', 'max:365'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
        ]);

        // A receipt accepted into the workshop advances to repairing by default.
        // The default also keeps older clients from failing when they omit status.
        $status = (string) $request->input('status', 'repairing');
        $waitingMode = $status === 'cancelled'
            ? 'repair_now'
            : (string) $request->input('waiting_mode', $request->boolean('waiting_for_parts') ? 'parts' : 'repair_now');
        $isWaiting = in_array($waitingMode, ['parts', 'wait_repair'], true);
        if (! in_array($repair->status, ['pending', 'repairing'], true)) {
            throw ValidationException::withMessages(['status' => 'Phiếu này không còn trong giai đoạn tiếp nhận hoặc đang sửa.']);
        }

        $wasWaitingForParts = (bool) RepairTimeline::query()
            ->where('repair_id', $repair->id)
            ->latest('id')
            ->value('waiting_for_parts');
        $waitingForParts = $status !== 'cancelled' && $isWaiting;

        $progressIssues = $request->input('issue', []);
        $repairChanges = ['status' => $status];
        if ($progressIssues !== []) {
            $repairChanges['issue'] = array_values(array_unique(array_merge($repair->issue ?? [], $progressIssues)));
        }
        $repair->update($repairChanges);

        /*
        |--------------------------------------------------------------------------
        | Tiêu đề timeline
        |--------------------------------------------------------------------------
        */

        $titles = [
            'cancelled' => 'Đã hủy phiếu sửa',
        ];
        $title = $status === 'cancelled'
            ? $titles['cancelled']
            : ($waitingMode === 'parts'
                ? 'Chờ linh kiện'
                : ($waitingMode === 'wait_repair'
                    ? 'Tạm chờ sửa'
                    : ($wasWaitingForParts ? 'Tiếp tục sửa sau khi chờ' : 'Đang sửa')));

        $timeline = RepairTimeline::create([

            'repair_id' =>
                $repair->id,

            'user_id' =>
                auth()->id(),

            'status' => $status,

            'title' => $title,

            'description' =>
                $request->input('description'),
            'issue' => $request->input('issue', []),
            'parts_needed' => $waitingMode === 'parts' ? $request->input('parts_needed') : null,
            'expected_days' => $waitingMode === 'parts' ? $request->input('expected_days') : null,
            'waiting_for_parts' => $waitingForParts,
            'waiting_mode' => $waitingMode,
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('repairs', 'public');
                RepairImage::create([
                    'repair_id' => $repair->id,
                    'timeline_id' => $timeline->id,
                    'image_path' => $path,
                ]);
            }
        }

        $statusLabel = $status === 'cancelled'
            ? 'đã hủy phiếu'
            : ($waitingMode === 'parts' ? 'đang chờ linh kiện' : ($waitingMode === 'wait_repair' ? 'tạm chờ sửa' : 'đang sửa'));
        $this->notifyRepairEvent($repair, 'Cập nhật phiếu sửa ' . $repair->code, 'Phiếu ' . $repair->code . ' chuyển sang trạng thái ' . $statusLabel . '.', $status === 'cancelled' ? 'warning' : 'repair');

        return back();
    }

    /** Hoàn tất sửa, ghi nhận linh kiện và trừ kho. */
    public function complete(Request $request, Repair $repair): JsonResponse
    {
        $data = $request->validate([
            'billing_type' => ['required', 'in:service,warranty'],
            'decline_warranty' => ['nullable', 'boolean'],
            'decline_reason' => ['nullable', 'required_if:decline_warranty,1', 'string', 'min:5', 'max:2000'],
            'parts' => ['nullable', 'array'],
            'parts.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'parts.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'parts.*.quantity' => ['required', 'integer', 'min:1'],
            'parts.*.unit_price' => ['required', 'numeric', 'min:0'],
            'labor_cost' => ['required', 'numeric', 'min:0'],
            'surcharge' => ['required', 'numeric', 'min:0'],
            'warranty_covered_amount' => ['nullable', 'numeric', 'min:0'],
            'repair_warranty_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        if (! in_array($repair->status, ['pending', 'repairing'], true)) {
            throw ValidationException::withMessages(['status' => 'Chỉ phiếu mới tiếp nhận hoặc đang sửa mới có thể hoàn tất.']);
        }
        $latestProgress = RepairTimeline::query()->where('repair_id', $repair->id)->latest('id');
        $waitingMode = $latestProgress->value('waiting_mode');
        if ($waitingMode ? in_array($waitingMode, ['parts', 'wait_repair'], true) : (bool) $latestProgress->value('waiting_for_parts')) {
            throw ValidationException::withMessages(['status' => 'Phiếu đang tạm chờ. Hãy mở lại tiến trình và xác nhận tiếp tục sửa trước khi hoàn tất.']);
        }

        $completed = DB::transaction(function () use ($repair, $data): Repair {
            $repair = Repair::query()->with('customer')->lockForUpdate()->findOrFail($repair->id);
            if (! in_array($repair->status, ['pending', 'repairing'], true)) {
                throw ValidationException::withMessages(['status' => 'Phiếu sửa đã được cập nhật ở nơi khác.']);
            }
            $latestProgress = RepairTimeline::query()->where('repair_id', $repair->id)->latest('id');
            $waitingMode = $latestProgress->value('waiting_mode');
            if ($waitingMode ? in_array($waitingMode, ['parts', 'wait_repair'], true) : (bool) $latestProgress->value('waiting_for_parts')) {
                throw ValidationException::withMessages(['status' => 'Phiếu đang tạm chờ. Hãy mở lại tiến trình và xác nhận tiếp tục sửa trước khi hoàn tất.']);
            }

            $hasWarrantySource = filled($repair->warranty_source_type) && filled($repair->warranty_source_id);
            $declineWarranty = (bool) ($data['decline_warranty'] ?? false);
            $warranty = null;
            if ($declineWarranty) {
                if ($data['billing_type'] === 'warranty') {
                    throw ValidationException::withMessages(['decline_reason' => 'Không thể từ chối bảo hành đồng thời xử lý phiếu theo diện bảo hành.']);
                }
                if (! $hasWarrantySource || $repair->warranty_status === 'declined') {
                    throw ValidationException::withMessages(['decline_reason' => 'Phiếu này không có căn cứ bảo hành hợp lệ để từ chối.']);
                }
                $this->voidWarrantySource($repair, $data['decline_reason']);
            } elseif ($data['billing_type'] === 'warranty') {
                if (! $hasWarrantySource || ! $repair->customer) {
                    throw ValidationException::withMessages(['billing_type' => 'Phiếu chưa có căn cứ bảo hành hợp lệ.']);
                }
                $warranty = $this->resolveWarrantySource(
                    $repair->customer,
                    $repair->warranty_source_type,
                    (int) $repair->warranty_source_id,
                    (string) ($repair->imei ?: $repair->serial ?: ''),
                );
            }

            $repair->parts()->delete();
            $partsTotal = 0.0;
            foreach ($data['parts'] ?? [] as $line) {
                $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);
                if (! $product->is_active || $product->product_type === 'imei' || $product->manage_stock_by_serial) {
                    throw ValidationException::withMessages(['parts' => "Sản phẩm {$product->name} không thể dùng làm linh kiện xuất kho."]);
                }

                $variantId = $line['variant_id'] ?? null;
                $hasVariants = $product->variants()->where('is_active', true)->exists();
                $variant = null;
                if ($variantId) {
                    $variant = ProductVariant::query()->where('product_id', $product->id)->where('is_active', true)->lockForUpdate()->findOrFail($variantId);
                    if ($variant->stock < $line['quantity'] || $product->stock < $line['quantity']) {
                        throw ValidationException::withMessages(['parts' => "Tồn kho của {$product->name} không đủ."]);
                    }
                    $variant->decrement('stock', $line['quantity']);
                    $product->decrement('stock', $line['quantity']);
                } else {
                    if ($hasVariants) {
                        throw ValidationException::withMessages(['parts' => "Vui lòng chọn phân loại cho {$product->name}."]);
                    }
                    if ($product->stock < $line['quantity']) {
                        throw ValidationException::withMessages(['parts' => "Tồn kho của {$product->name} không đủ."]);
                    }
                    $product->decrement('stock', $line['quantity']);
                }

                $unitPrice = (float) $line['unit_price'];
                $subtotal = $unitPrice * (int) $line['quantity'];
                $partsTotal += $subtotal;
                RepairPart::query()->create([
                    'repair_id' => $repair->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name . ($variant ? ' · ' . collect($variant->attributes ?? [])->values()->implode(' / ') : ''),
                    'sku' => $variant?->sku ?: $product->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $labor = (float) $data['labor_cost'];
            $surcharge = (float) $data['surcharge'];
            $grossTotal = $partsTotal + $labor + $surcharge;
            $coveredAmount = $data['billing_type'] === 'warranty' && ! $declineWarranty
                ? min($grossTotal, (float) ($data['warranty_covered_amount'] ?? 0))
                : 0.0;
            $total = max(0, $grossTotal - $coveredAmount);
            $finalType = $data['billing_type'] === 'warranty' && ! $declineWarranty ? 'warranty' : 'repair';
            $warrantyStatus = $declineWarranty ? 'declined' : ($finalType === 'warranty' ? 'accepted' : ($hasWarrantySource ? 'service' : null));
            $repair->update([
                'intake_type' => $finalType,
                'warranty_status' => $warrantyStatus,
                'warranty_expires_at' => $warranty['expires_at'] ?? $repair->warranty_expires_at,
                'warranty_declined_at' => $declineWarranty ? now() : $repair->warranty_declined_at,
                'warranty_decline_reason' => $declineWarranty ? $data['decline_reason'] : $repair->warranty_decline_reason,
                'parts_total' => $partsTotal,
                'labor_cost' => $labor,
                'surcharge' => $surcharge,
                'final_cost' => $total,
                'warranty_covered_amount' => $coveredAmount,
                'repair_warranty_days' => (int) $data['repair_warranty_days'],
                'status' => 'done',
                'completed_at' => now(),
            ]);
            RepairTimeline::query()->create([
                'repair_id' => $repair->id,
                'user_id' => auth()->id(),
                'status' => 'done',
                'title' => 'Hoàn tất sửa',
                'description' => 'Linh kiện: ' . number_format($partsTotal, 0, ',', '.') . 'đ · Công sửa: ' . number_format($labor, 0, ',', '.') . 'đ · Phụ phí: ' . number_format($surcharge, 0, ',', '.') . 'đ'
                    . ($finalType === 'warranty' ? ' · Loại xử lý: bảo hành' : ' · Loại xử lý: sửa dịch vụ')
                    . ($coveredAmount > 0 ? ' · Tổng chi phí: ' . number_format($grossTotal, 0, ',', '.') . 'đ · Được bảo hành: ' . number_format($coveredAmount, 0, ',', '.') . 'đ' : '')
                    . ' · Khách thanh toán: ' . number_format($total, 0, ',', '.') . 'đ'
                    . ($declineWarranty ? ' · Từ chối bảo hành: ' . $data['decline_reason'] : '')
                    . ($data['repair_warranty_days'] > 0 ? ' · Bảo hành lần sửa: ' . $data['repair_warranty_days'] . ' ngày từ lúc trả máy' : ''),
            ]);
            return $repair;
        });

        $this->notifyRepairEvent($completed, 'Đã hoàn tất sửa chữa', 'Phiếu ' . $completed->code . ' (' . $completed->device_name . ') đã hoàn tất, tổng chi phí ' . number_format((float) $completed->final_cost, 0, ',', '.') . ' đ.');

        return response()->json(['success' => true, 'repair_id' => $completed->id]);
    }

    /** Thu tiền theo modal POS và chuyển sang đã trả khách. */
    public function returnToCustomer(Request $request, Repair $repair): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,bank,card'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'pay_old_debt' => ['nullable', 'boolean'],
        ]);
        if ($repair->status !== 'done') {
            throw ValidationException::withMessages(['status' => 'Chỉ phiếu đã hoàn tất sửa mới có thể trả khách.']);
        }

        DB::transaction(function () use ($repair, $data): void {
            $repair = Repair::query()->with('customer')->lockForUpdate()->findOrFail($repair->id);
            if ($repair->status !== 'done') {
                throw ValidationException::withMessages(['status' => 'Phiếu sửa đã được cập nhật ở nơi khác.']);
            }
            $customer = $repair->customer;
            if ($customer) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            }
            $total = (float) $repair->final_cost;
            $paid = (float) $data['paid_amount'];
            if (! $customer && $paid < $total) {
                throw ValidationException::withMessages(['paid_amount' => 'Khách lẻ cần thanh toán đủ trước khi trả máy.']);
            }

            $newDebt = $customer ? max(0, $total - min($total, $paid)) : 0;
            $oldDebtPaid = $customer && ($data['pay_old_debt'] ?? false)
                ? min((float) $customer->debt_balance, max(0, $paid - $total))
                : 0;
            if ($newDebt > 0) {
                CustomerDebt::query()->create([
                    'customer_id' => $customer->id,
                    'type' => 'increase',
                    'amount' => $newDebt,
                    'source_type' => Repair::class,
                    'source_id' => $repair->id,
                    'note' => 'Còn thiếu khi thanh toán phiếu sửa ' . $repair->code,
                ]);
                $customer->increment('debt_balance', $newDebt);
            }
            if ($oldDebtPaid > 0) {
                CustomerDebt::query()->create([
                    'customer_id' => $customer->id,
                    'type' => 'decrease',
                    'amount' => $oldDebtPaid,
                    'source_type' => Repair::class,
                    'source_id' => $repair->id,
                    'note' => 'Trích tiền thừa phiếu sửa ' . $repair->code . ' để thanh toán nợ cũ',
                ]);
                $customer->decrement('debt_balance', $oldDebtPaid);
            }
            $change = max(0, $paid - $total - $oldDebtPaid);
            $returnedAt = now();
            $repairWarrantyExpiresAt = (int) $repair->repair_warranty_days > 0
                ? $returnedAt->copy()->addDays((int) $repair->repair_warranty_days)
                : null;
            $repair->update([
                'status' => 'returned',
                'paid_amount' => $paid,
                'change_amount' => $change,
                'payment_method' => $data['payment_method'],
                'payment_note' => $data['note'] ?? null,
                'returned_at' => now(),
                'repair_warranty_started_at' => $repair->repair_warranty_days > 0 ? $returnedAt : null,
                'repair_warranty_expires_at' => $repairWarrantyExpiresAt,
                'warranty_status' => $repair->intake_type === 'warranty' ? 'accepted' : $repair->warranty_status,
            ]);
            RepairTimeline::query()->create([
                'repair_id' => $repair->id,
                'user_id' => auth()->id(),
                'status' => 'returned',
                'title' => 'Đã trả khách',
                'description' => 'Đã thu ' . number_format($paid, 0, ',', '.') . 'đ · ' . $data['payment_method']
                    . ($change > 0 ? ' · Tiền thừa ' . number_format($change, 0, ',', '.') . 'đ' : '')
                    . ($repairWarrantyExpiresAt ? ' · Bảo hành sửa chữa đến ' . $repairWarrantyExpiresAt->format('d/m/Y') : '')
                    . (! empty($data['note']) ? ' · ' . $data['note'] : ''),
            ]);
        });

        $repair->refresh();
        $this->notifyRepairEvent($repair, 'Đã trả máy cho khách', 'Phiếu ' . $repair->code . ' (' . $repair->device_name . ') đã thanh toán và trả khách.');

        return response()->json(['success' => true]);
    }

    /** Đánh dấu một lần tiếp nhận bảo hành bị từ chối và vô hiệu hóa hạn gốc. */
    public function declineWarranty(Request $request, Repair $repair): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $declined = DB::transaction(function () use ($repair, $data): Repair {
            $claim = Repair::query()->lockForUpdate()->findOrFail($repair->id);
            if (! in_array($claim->status, ['pending', 'repairing'], true) || ! filled($claim->warranty_source_type) || ! filled($claim->warranty_source_id)) {
                throw ValidationException::withMessages(['reason' => 'Chỉ phiếu bảo hành đang tiếp nhận hoặc sửa mới được từ chối.']);
            }
            if ($claim->warranty_status === 'declined') {
                throw ValidationException::withMessages(['reason' => 'Yêu cầu bảo hành này đã được từ chối.']);
            }

            $this->voidWarrantySource($claim, $data['reason']);

            $claim->update([
                'warranty_status' => 'declined',
                'warranty_declined_at' => now(),
                'warranty_decline_reason' => $data['reason'],
            ]);
            RepairTimeline::query()->create([
                'repair_id' => $claim->id,
                'user_id' => auth()->id(),
                'status' => $claim->status,
                'title' => 'Từ chối bảo hành · đã vô hiệu hạn bảo hành',
                'description' => $data['reason'],
            ]);

            return $claim;
        });

        $this->notifyRepairEvent(
            $declined,
            'Đã từ chối yêu cầu bảo hành',
            'Phiếu ' . $declined->code . ' bị từ chối bảo hành. Hạn bảo hành gốc đã bị vô hiệu hóa.',
            'warning'
        );

        return response()->json(['success' => true]);
    }

    /** Lưu lý do từ chối và vô hiệu hóa hạn bảo hành trên giao dịch gốc. */
    private function ensureImeiHasNoOpenRepair(?string $imei, ?int $exceptRepairId = null): void
    {
        $identifier = mb_strtolower(trim((string) $imei));
        if ($identifier === '') {
            return;
        }

        $openRepair = Repair::query()
            ->whereIn('status', ['pending', 'repairing'])
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(imei) = ?', [$identifier])
                ->orWhereRaw('LOWER(serial) = ?', [$identifier]))
            ->when($exceptRepairId, fn ($query) => $query->where('id', '!=', $exceptRepairId))
            ->first(['id', 'code']);

        if ($openRepair) {
            throw ValidationException::withMessages([
                'imei' => "IMEI/Serial này đã có phiếu {$openRepair->code} chưa hoàn tất. Hãy tiếp tục cập nhật phiếu hiện tại.",
            ]);
        }
    }

    private function voidWarrantySource(Repair $claim, string $reason): void
    {
        if ($claim->warranty_source_type === 'sale_item') {
            $saleItem = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sale_items.id', $claim->warranty_source_id)
                ->where(fn ($query) => $query->where('sales.customer_id', $claim->customer_id)->orWhereNull('sales.customer_id'))
                ->lockForUpdate()
                ->first(['sale_items.product_imei_id']);
            if (! $saleItem?->product_imei_id) {
                throw ValidationException::withMessages(['decline_reason' => 'Không tìm thấy thiết bị gốc để vô hiệu hóa bảo hành.']);
            }
            $imei = ProductImei::query()->lockForUpdate()->findOrFail($saleItem->product_imei_id);
            if ($imei->customer_warranty_voided_at) {
                throw ValidationException::withMessages(['decline_reason' => 'Hạn bảo hành mua hàng đã bị vô hiệu trước đó.']);
            }
            $imei->update([
                'customer_warranty_voided_at' => now(),
                'customer_warranty_void_reason' => $reason,
                'customer_warranty_voided_by' => auth()->id(),
            ]);
            return;
        }

        if ($claim->warranty_source_type === 'repair') {
            $sourceRepair = Repair::query()->where('customer_id', $claim->customer_id)->lockForUpdate()->findOrFail($claim->warranty_source_id);
            if ($sourceRepair->repair_warranty_voided_at) {
                throw ValidationException::withMessages(['decline_reason' => 'Hạn bảo hành sửa chữa đã bị vô hiệu trước đó.']);
            }
            $sourceRepair->update([
                'repair_warranty_voided_at' => now(),
                'repair_warranty_void_reason' => $reason,
                'repair_warranty_voided_by' => auth()->id(),
            ]);
            return;
        }

        throw ValidationException::withMessages(['decline_reason' => 'Không xác định được nguồn bảo hành để vô hiệu hóa.']);
    }

    /** Xác minh quyền bảo hành trên giao dịch mua hoặc phiếu sửa gốc. */
    private function resolveWarrantySource(Customer $customer, string $sourceType, int $sourceId, string $imeiCode): array
    {
        if (trim($imeiCode) === '') {
            throw ValidationException::withMessages(['warranty_source_id' => 'Cần có IMEI/Serial để xác minh bảo hành.']);
        }

        if ($sourceType === 'sale_item') {
            $source = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->leftJoin('product_imeis', 'product_imeis.id', '=', 'sale_items.product_imei_id')
                ->where('sale_items.id', $sourceId)
                ->where(fn ($query) => $query->where('sales.customer_id', $customer->id)->orWhereNull('sales.customer_id'))
                ->where('sales.status', '!=', 'cancelled')
                ->first([
                    'sale_items.id', 'sales.code', 'sales.created_at as purchased_at',
                    'products.warranty_days', 'sale_items.product_imei_id',
                    'product_imeis.imei', 'product_imeis.serial',
                    'product_imeis.customer_warranty_expires_at',
                    'product_imeis.customer_warranty_days',
                    'product_imeis.customer_warranty_voided_at', 'product_imeis.customer_warranty_void_reason',
                ]);

            if (! $source || ! $source->product_imei_id || ! in_array($imeiCode, array_filter([$source->imei, $source->serial]), true)) {
                throw ValidationException::withMessages(['warranty_source_id' => 'Không khớp thiết bị với hóa đơn bảo hành đã chọn.']);
            }

            if ($source->customer_warranty_voided_at) {
                throw ValidationException::withMessages(['warranty_source_id' => 'Hạn bảo hành đã bị vô hiệu: ' . ($source->customer_warranty_void_reason ?: 'không còn hiệu lực')]);
            }

            $expiresAt = $source->customer_warranty_days !== null
                ? ($source->customer_warranty_expires_at
                    ? Carbon::parse($source->customer_warranty_expires_at)
                    : ((int) $source->customer_warranty_days > 0
                        ? Carbon::parse($source->purchased_at)->addDays((int) $source->customer_warranty_days)
                        : null))
                : ($source->customer_warranty_expires_at
                    ? Carbon::parse($source->customer_warranty_expires_at)
                    : ((int) $source->warranty_days > 0
                        ? Carbon::parse($source->purchased_at)->addDays((int) $source->warranty_days)
                        : null));
            if (! $expiresAt || $expiresAt->endOfDay()->lt(now())) {
                throw ValidationException::withMessages(['warranty_source_id' => 'Máy không còn thời hạn bảo hành theo hóa đơn đã chọn.']);
            }

            return [
                'source_type' => 'sale_item',
                'source_id' => $source->id,
                'expires_at' => $expiresAt,
                'reference' => 'hóa đơn ' . $source->code,
            ];
        }

        if ($sourceType === 'repair') {
            $source = Repair::query()
                ->where('id', $sourceId)
                ->where('customer_id', $customer->id)
                ->first();

            if (! $source || ! $source->returned_at || ! in_array($imeiCode, array_filter([$source->imei, $source->serial]), true)) {
                throw ValidationException::withMessages(['warranty_source_id' => 'Không khớp thiết bị với phiếu sửa bảo hành đã chọn.']);
            }
            if ($source->repair_warranty_voided_at) {
                throw ValidationException::withMessages(['warranty_source_id' => 'Hạn bảo hành sửa chữa đã bị vô hiệu: ' . ($source->repair_warranty_void_reason ?: 'không còn hiệu lực')]);
            }
            if (! $source->repair_warranty_expires_at || $source->repair_warranty_expires_at->endOfDay()->lt(now())) {
                throw ValidationException::withMessages(['warranty_source_id' => 'Máy không còn thời hạn bảo hành theo phiếu sửa đã chọn.']);
            }

            return [
                'source_type' => 'repair',
                'source_id' => $source->id,
                'expires_at' => $source->repair_warranty_expires_at,
                'reference' => 'phiếu sửa ' . $source->code,
            ];
        }

        throw ValidationException::withMessages(['warranty_source_id' => 'Nguồn bảo hành không hợp lệ.']);
    }

    private function notifyRepairEvent(Repair $repair, string $title, string $message, string $type = 'repair'): void
    {
        Notification::send(
            User::query()->get(),
            new SystemNotification($title, $message, $type, route('repairs.index'))
        );
    }



        /**
     * In hóa đơn sửa chữa.
     */
    public function print(
        Repair $repair
    ) {

        $repair->load([
            'images',
        ]);

        return view(

            'prints.repair',

            [

                'repair' => $repair,
            ]
        );
    }

        /**
     * Cập nhật phiếu sửa chữa.
     */
    public function update(
        Request $request,
        Repair $repair
    ): RedirectResponse {

        $validated = $request->validate([

            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($request->input('customer_id'))],
            'identity_card' => ['nullable', 'string', 'max:20'],
            'contact_phone' => ['nullable', 'string', 'max:20'],

            'device_name' => [
                'required',
                'string',
                'max:255',
            ],

            'imei' => [
                'nullable',
                'string',
                'max:255',
            ],

            'serial' => ['nullable', 'string', 'max:255'],
            'screen_password' => ['nullable', 'string', 'max:255'],
            'screen_pattern' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'string', 'max:255'],
            'account_email' => ['nullable', 'string', 'max:255'],
            'account_password' => ['nullable', 'string', 'max:255'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', 'max:255'],

            'issue' => [
                'nullable',
                'array',
            ],

            'repair_request' => [
                'nullable',
                'string',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            'estimated_cost' => [
                'nullable', 'numeric', 'min:0',
            ],

            'images.*' => [
                'nullable',
                'image',
                'max:5120',
            ],
        ]);

        DB::beginTransaction();

        try {

            $this->ensureImeiHasNoOpenRepair($validated['imei'] ?? null, $repair->id);

            $customer = !empty($validated['customer_id'])
                ? Customer::query()->findOrFail($validated['customer_id'])
                : (!empty($validated['customer_phone'])
                    ? Customer::query()->where('phone', $validated['customer_phone'])->first()
                    : null);

            if (!$customer) {
                $customer = app(\App\Services\CustomerService::class)->create([
                    'full_name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'] ?? null,
                    'cccd' => $validated['identity_card'] ?? null,
                ]);
            } else {
                $customer->update([
                    'full_name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'] ?? null,
                    'cccd' => $validated['identity_card'] ?? null,
                ]);
            }

            $repair->update([

                'customer_id' => $customer->id,
                'contact_phone' => $validated['contact_phone'] ?? null,

                'device_name' =>
                    $validated['device_name'],

                'imei' =>
                    $validated['imei'] ?? null,

                'serial' => $validated['serial'] ?? null,

                'screen_password' => $validated['screen_password'] ?? null,

                'screen_pattern' => $validated['screen_pattern'] ?? null,

                'account_type' => $validated['account_type'] ?? null,

                'account_email' => $validated['account_email'] ?? null,

                'account_password' => $validated['account_password'] ?? null,

                'issue' =>
                    $validated['issue'] ?? [],

                'accessories' => $validated['accessories'] ?? [],

                'repair_request' =>
                    $validated['repair_request']
                    ?? null,

                'note' =>
                    $validated['note']
                    ?? null,

                'estimated_cost' =>
                    $validated['estimated_cost']
                    ?? null,

            ]);

            /*
            |--------------------------------------------------------------------------
            | Upload ảnh mới
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('images')) {

                foreach (
                    $request->file('images')
                    as $image
                ) {

                    $path = $image->store(
                        'repairs',
                        'public'
                    );

                    RepairImage::create([

                        'repair_id' =>
                            $repair->id,

                        'image_path' =>
                            $path,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Timeline
            |--------------------------------------------------------------------------
            */

            RepairTimeline::create([

                'repair_id' =>
                    $repair->id,

                'user_id' =>
                    auth()->id(),

                'status' => $repair->status,

                'title' =>
                    'Cập nhật phiếu sửa',

                'description' =>
                    'Kỹ thuật viên cập nhật thông tin sửa chữa',
            ]);

            DB::commit();

            return back();

        } catch (\Throwable $e) {

            DB::rollBack();

            throw $e;
        }
    }
}
