<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductImeiHistory;
use App\Models\Repair;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProductImeiController extends Controller
{
    public function index(
        Product $product
    ): Response {

        $product->load('imeis');

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

        $product->increment('stock');

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

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'imei' => ['required', 'string', 'max:100'],
        ]);

        $imeiCode = trim($data['imei']);

        $imei = ProductImei::query()
            ->with(['product:id,name,sku,warranty_days', 'variant:id,product_id,sku,attributes'])
            ->where(function ($query) use ($imeiCode): void {
                $query
                    ->where('imei', $imeiCode)
                    ->orWhere('serial', $imeiCode);
            })
            ->first();

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
            ->with('technician:id,name')
            ->where(function ($query) use ($lookupCodes): void {
                $query
                    ->whereIn('imei', $lookupCodes)
                    ->orWhereIn('serial', $lookupCodes);
            })
            ->latest('id')
            ->get([
                'id',
                'code',
                'device_name',
                'imei',
                'contact_phone',
                'repair_request',
                'estimated_cost',
                'final_cost',
                'status',
                'note',
                'technician_id',
                'received_at',
                'completed_at',
                'returned_at',
                'created_at',
            ]);

        $importHistories = ProductImeiHistory::query()
            ->where('imei', $imei?->imei ?: $imeiCode)
            ->where('type', 'import')
            ->latest('happened_at')
            ->latest('id')
            ->get();

        return response()->json([
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
            'summary' => [
                'import_count' => $importHistories->count() ?: ($imei ? 1 : 0),
                'sale_count' => $saleItems->count(),
                'repair_count' => $repairs->count(),
            ],
            'imports' => $importHistories->map(fn (ProductImeiHistory $history) => [
                'id' => $history->id,
                'code' => $history->meta['import_code'] ?? null,
                'cost_price' => $history->cost_price,
                'sell_price' => $history->sell_price,
                'supplier_id' => $history->meta['supplier_id'] ?? null,
                'happened_at' => $history->happened_at,
                'created_at' => $history->created_at,
            ])->values(),
            'sales' => $saleItems->map(fn (SaleItem $item) => [
                'id' => $item->id,
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
                'repair_request' => $repair->repair_request,
                'estimated_cost' => $repair->estimated_cost,
                'final_cost' => $repair->final_cost,
                'status' => $repair->status,
                'technician_name' => $repair->technician?->name,
                'received_at' => $repair->received_at,
                'completed_at' => $repair->completed_at,
                'returned_at' => $repair->returned_at,
                'note' => $repair->note,
            ])->values(),
        ]);
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
