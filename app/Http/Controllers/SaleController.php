<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductVariant;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\CustomerDebt;
use App\Services\Stock\ImeiStockSynchronizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function searchProducts(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) return response()->json([]);

        return Product::query()
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery
                        ->where('barcode', $term)
                        ->orWhere('sku', 'like', "%{$term}%"));
            })
            ->with(['variants' => fn ($query) => $query->where('is_active', true)])
            ->limit(12)
            ->get(['id', 'name', 'sku', 'sell_price', 'stock', 'product_type', 'manage_stock_by_serial'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->sell_price,
                'stock' => (int) $product->stock,
                'is_imei' => $product->product_type === 'imei' || (bool) $product->manage_stock_by_serial,
                'variants' => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $product->name . ' · ' . collect($variant->attributes ?? [])->filter()->join(' / '),
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'price' => (float) ($variant->sell_price ?: $product->sell_price),
                    'stock' => (int) $variant->stock,
                ])->values(),
            ]);
    }

    public function searchCustomers(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) return response()->json([]);

        return Customer::query()
            ->select('id', 'code', 'full_name', 'phone')
            ->where(function ($query) use ($term): void {
                $query->where('full_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            })
            ->orderBy('full_name')
            ->limit(10)
            ->get();
    }

    public function edit(Sale $sale): Response
    {
        $sale->load(['customer', 'items.product.unit', 'items.variant', 'items.productImei', 'items.gifts']);
        $blockedReason = $this->saleEditBlockedReason($sale);

        return Inertia::render('Sales/Edit', [
            'sale' => $sale,
            'customers' => Customer::query()->select('id', 'full_name', 'phone')->orderBy('full_name')->get(),
            'blockedReason' => $blockedReason,
        ]);
    }

    public function update(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percent,amount'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($sale, $data): void {
            $sale = Sale::query()->lockForUpdate()->with(['items.product', 'items.variant', 'items.productImei', 'items.gifts'])->findOrFail($sale->id);
            $reason = $this->saleEditBlockedReason($sale);
            if ($reason) throw \Illuminate\Validation\ValidationException::withMessages(['sale' => $reason]);

            $oldDebt = max(0, (float) $sale->grand_total - (float) $sale->paid_amount);
            $oldCustomerId = $sale->customer_id;
            $subtotal = 0.0;
            $discount = 0.0;
            $newItems = [];

            foreach ($data['items'] as $row) {
                $product = Product::query()->lockForUpdate()->findOrFail($row['product_id']);
                if ($product->product_type === 'imei' || $product->manage_stock_by_serial) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Không thể thêm mặt hàng quản lý IMEI khi sửa hóa đơn.']);
                }
                $variant = null;
                if (!empty($row['variant_id'])) {
                    $variant = ProductVariant::query()->lockForUpdate()->where('product_id', $product->id)->where('is_active', true)->findOrFail($row['variant_id']);
                }
                $quantity = (int) $row['quantity'];
                $price = round((float) $row['unit_price'], 2);
                $lineTotal = $price * $quantity;
                $type = $row['discount_type'] ?? null;
                $value = round((float) ($row['discount_value'] ?? 0), 2);
                $lineDiscount = $type === 'percent' ? $lineTotal * $value / 100 : ($type === 'amount' ? $value : 0);
                if ($lineDiscount > $lineTotal || ($type === 'percent' && $value > 100)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Mức giảm giá không hợp lệ.']);
                }
                $subtotal += $lineTotal;
                $discount += $lineDiscount;
                $newItems[] = compact('product', 'variant', 'quantity', 'price', 'type', 'value', 'lineDiscount', 'lineTotal');
            }

            $oldStockDelta = [];
            $oldSoldDelta = [];
            foreach ($sale->items as $item) {
                $key = ($item->variant_id ? 'v' . $item->variant_id : 'p' . $item->product_id);
                $oldStockDelta[$key] = ($oldStockDelta[$key] ?? 0) + (int) ($item->base_quantity ?: $item->quantity);
                $oldSoldDelta[$item->product_id] = ($oldSoldDelta[$item->product_id] ?? 0) + (int) $item->quantity;
            }
            $newStockDelta = [];
            $newSoldDelta = [];
            foreach ($newItems as $item) {
                $key = $item['variant'] ? 'v' . $item['variant']->id : 'p' . $item['product']->id;
                $newStockDelta[$key] = ($newStockDelta[$key] ?? 0) + $item['quantity'];
                $newSoldDelta[$item['product']->id] = ($newSoldDelta[$item['product']->id] ?? 0) + $item['quantity'];
            }
            foreach (array_unique(array_merge(array_keys($oldStockDelta), array_keys($newStockDelta))) as $key) {
                $delta = ($oldStockDelta[$key] ?? 0) - ($newStockDelta[$key] ?? 0);
                if (!$delta) continue;
                $isVariant = str_starts_with($key, 'v');
                $id = (int) substr($key, 1);
                $stockItem = $isVariant ? ProductVariant::query()->lockForUpdate()->findOrFail($id) : Product::query()->lockForUpdate()->findOrFail($id);
                if ($delta < 0 && !filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN) && (int) $stockItem->stock < abs($delta)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Tồn kho không đủ cho số lượng mới.']);
                }
                $stockItem->increment('stock', $delta);
                if ($isVariant) Product::query()->whereKey($stockItem->product_id)->increment('stock', $delta);
            }
            foreach (array_unique(array_merge(array_keys($oldSoldDelta), array_keys($newSoldDelta))) as $productId) {
                $soldDelta = ($newSoldDelta[$productId] ?? 0) - ($oldSoldDelta[$productId] ?? 0);
                if ($soldDelta > 0) Product::query()->whereKey($productId)->increment('sold_count', $soldDelta);
                elseif ($soldDelta < 0) Product::query()->whereKey($productId)->update(['sold_count' => DB::raw('GREATEST(0, sold_count - ' . abs($soldDelta) . ')')]);
            }

            $sale->items()->delete();
            foreach ($newItems as $item) {
                $sale->items()->create([
                    'product_id' => $item['product']->id,
                    'variant_id' => $item['variant']?->id,
                    'unit_id' => $item['product']->unit_id,
                    'unit_name' => $item['product']->unit?->short_name ?: $item['product']->unit?->name,
                    'quantity' => $item['quantity'],
                    'base_quantity' => $item['quantity'],
                    'conversion_factor' => 1,
                    'unit_price' => $item['price'],
                    'discount_type' => $item['type'],
                    'discount_value' => $item['value'],
                    'discount_amount' => $item['lineDiscount'],
                    'subtotal' => $item['lineTotal'] - $item['lineDiscount'],
                ]);
            }

            $newTotal = max(0, round($subtotal - $discount + (float) $sale->tax, 2));
            $newDebt = max(0, $newTotal - (float) $sale->paid_amount);
            $newCustomerId = $data['customer_id'] ?? null;
            if (!$newCustomerId && $newDebt > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['customer_id' => 'Khách lẻ cần thanh toán đủ tiền; hãy chọn khách hàng để ghi nhận công nợ.']);
            }
            $sale->update([
                'customer_id' => $newCustomerId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'grand_total' => $newTotal,
                'change_amount' => max(0, (float) $sale->paid_amount - $newTotal),
            ]);

            if ($oldCustomerId || $newCustomerId) {
                $debtEntry = CustomerDebt::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->where('type', 'increase')->first();
                if ($debtEntry) {
                    Customer::query()->whereKey($oldCustomerId)->decrement('debt_balance', $oldDebt);
                    if ($newCustomerId) Customer::query()->whereKey($newCustomerId)->increment('debt_balance', $newDebt);
                    if ($newCustomerId) $debtEntry->update(['customer_id' => $newCustomerId, 'amount' => $newDebt]);
                    else $debtEntry->delete();
                } elseif ($oldDebt > 0 || $newDebt > 0) {
                    if ($oldCustomerId) Customer::query()->whereKey($oldCustomerId)->decrement('debt_balance', $oldDebt);
                    if ($newCustomerId) {
                        Customer::query()->whereKey($newCustomerId)->increment('debt_balance', $newDebt);
                        if ($newDebt > 0) CustomerDebt::query()->create(['customer_id' => $newCustomerId, 'type' => 'increase', 'amount' => $newDebt, 'source_type' => Sale::class, 'source_id' => $sale->id, 'note' => 'Điều chỉnh công nợ hóa đơn ' . $sale->code]);
                    }
                }
            }
        });

        return back()->with('success', 'Đã cập nhật hóa đơn.');
    }

    private function saleEditBlockedReason(Sale $sale): ?string
    {
        if ($sale->status !== 'completed') return 'Chỉ có thể sửa hóa đơn đang hoàn thành.';
        if (CustomerDebt::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->where('type', 'decrease')->exists()) return 'Hóa đơn đã có lịch sử thu công nợ nên không thể sửa.';
        if ($sale->items->contains(fn ($item) => $item->product_imei_id || $item->gifts->isNotEmpty())) return 'Hóa đơn có IMEI hoặc quà tặng; hiện chưa hỗ trợ sửa loại hóa đơn này.';
        return null;
    }

    // Danh sách hóa đơn bán hàng
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['completed', 'cancelled'], true)
            ? $request->query('status')
            : '';
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $sortBy = in_array($request->query('sort_by'), ['code', 'customer', 'user', 'total_amount', 'created_at'], true)
            ? $request->query('sort_by')
            : 'created_at';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 10;

        $query = Sale::query()
            ->with([
                'customer',
                'user',
                'items.product.conversionUnit',
                'items.variant',
                'items.productImei',
                'items.gifts.product',
                'items.gifts.productImei',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($saleQuery) use ($search): void {
                    $like = '%' . $search . '%';
                    $saleQuery->where('code', 'like', $like)
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->where('full_name', 'like', $like)
                            ->orWhere('phone', 'like', $like))
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like))
                        ->orWhereHas('items.productImei', fn ($imei) => $imei
                            ->where('imei', 'like', $like)
                            ->orWhere('serial', 'like', $like))
                        ->orWhereHas('items.product', fn ($product) => $product->where('name', 'like', $like));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo));

        $completedQuery = (clone $query)->where('status', 'completed');
        $stats = [
            'totalRevenue' => (float) (clone $completedQuery)->sum('grand_total'),
            'totalOrders' => (clone $query)->count(),
            'totalDebt' => (float) ((clone $completedQuery)->selectRaw('COALESCE(SUM(CASE WHEN grand_total > paid_amount THEN grand_total - paid_amount ELSE 0 END), 0) as total_debt')->value('total_debt') ?? 0),
        ];

        match ($sortBy) {
            'code' => $query->orderBy('code', $sortOrder),
            'customer' => $query->orderBy(
                Customer::query()->select('full_name')->whereColumn('customers.id', 'sales.customer_id'),
                $sortOrder
            ),
            'user' => $query->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'sales.user_id'),
                $sortOrder
            ),
            'total_amount' => $query->orderBy('grand_total', $sortOrder),
            default => $query->orderBy('created_at', $sortOrder),
        };

        $sales = $query
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Sales/Index',
            [
                'sales' => $sales,
                'stats' => $stats,
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'per_page' => $perPage,
                ],
            ]
        );
    }

    // Hiển thị chi tiết đơn hàng
    public function show(Sale $sale): Response
    {
        $sale->load([
            'customer',
            'user',
            'items.product.conversionUnit',
            'items.variant',
            'items.productImei',
            'items.gifts.product',
        ]);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
        ]);
    }

    // Hiển thị hóa đơn để in
    public function receipt(Sale $sale)
    {
        $sale->load([
            'customer',
            'user',
            'items.product.conversionUnit',
            'items.variant',
            'items.productImei',
            'items.gifts.product',
        ]);

        return Inertia::render(
            'Sales/Receipt',
            [
                'sale' => $sale,
            ]
        );
    }

    /**
     *  Xử lý Hủy hóa đơn & Hoàn trả tồn kho
     */
    public function cancel(Request $request, Sale $sale)
    {
        // 1. Kiểm tra nếu đơn hàng đã hủy trước đó
        if ($sale->status === 'cancelled') {
            DB::transaction(function () use ($sale): void {
                $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
                $this->reverseCancelledSaleDebt($lockedSale);
                $this->restoreCancelledSaleImeis($sale);
            });

            return back()->with('success', 'Hóa đơn đã hủy trước đó, hệ thống đã đồng bộ lại IMEI về kho.');
        }

        // 2. Cập nhật trạng thái & hoàn kho trong Transaction
        DB::transaction(function () use ($request, $sale) {
            // Đảm bảo lấy giá trị từ 'reason' hoặc 'cancel_reason'
            $reason = $request->input('reason') ?? $request->input('cancel_reason');

            $sale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($sale->status === 'cancelled') {
                $this->reverseCancelledSaleDebt($sale);
                return;
            }

            // Cập nhật thông tin hủy hóa đơn
            $sale->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $this->reverseCancelledSaleDebt($sale);

            // Nạp sản phẩm và IMEI
            $sale->load([
                'items.product',
                'items.variant',
                'items.productImei',
                'items.gifts.product',
            ]);

            foreach ($sale->items as $item) {
                $this->restoreSaleItemStock($item);
            }
        });

        return redirect()->route('sales.index')->with('success', 'Đã hủy hóa đơn thành công!');
    }

    private function reverseCancelledSaleDebt(Sale $sale): void
    {
        if (! $sale->customer_id) {
            return;
        }

        $note = 'Hoàn tác công nợ do hủy hóa đơn ' . $sale->code;
        if (CustomerDebt::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', 'decrease')
            ->where('note', $note)
            ->exists()) {
            return;
        }

        $increased = (float) CustomerDebt::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', 'increase')
            ->sum('amount');
        $decreasedForInvoice = (float) CustomerDebt::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', 'decrease')
            ->where(function ($query): void {
                $query->whereNull('note')->orWhere('note', 'not like', 'Trích tiền thừa hóa đơn%');
            })
            ->sum('amount');
        $remainingInvoiceDebt = max(0, $increased - $decreasedForInvoice);

        $customer = Customer::query()->lockForUpdate()->find($sale->customer_id);
        if (! $customer) {
            return;
        }

        $amount = min($remainingInvoiceDebt, max(0, (float) $customer->debt_balance));

        CustomerDebt::query()->create([
            'customer_id' => $customer->id,
            'type' => 'decrease',
            'amount' => $amount,
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'note' => $note,
        ]);
        if ($amount > 0) {
            $customer->decrement('debt_balance', $amount);
        }
    }

    private function restoreSaleItemStock(SaleItem $item): void
    {
        $quantity = max(1, (int) $item->quantity);
        $baseQuantity = max(1, (int) ($item->base_quantity ?: $quantity));

        if ($item->productImei) {
            $imei = ProductImei::query()
                ->lockForUpdate()
                ->find($item->productImei->id);

            if ($imei && ! $this->imeiBelongsToActiveSale($imei, $item->sale)) {
                $imei->update([
                    'status' => ProductImei::STATUS_IN_STOCK,
                    'sold_at' => null,
                ]);

                $this->syncImeiStock($imei);
            }
        } else {
            if ($item->variant) {
                ProductVariant::query()
                    ->whereKey($item->variant->id)
                    ->increment('stock', $baseQuantity);
            }

            if ($item->product) {
                Product::query()
                    ->whereKey($item->product->id)
                    ->increment('stock', $baseQuantity);
            }
        }

        if ($item->product) {
            $this->decrementSoldCount($item->product, $quantity);
        }

        foreach ($item->gifts as $gift) {
            $giftQuantity = max(1, (int) $gift->quantity);
            $giftBaseQuantity = max(1, (int) ($gift->base_quantity ?: $giftQuantity));

            if (! $gift->product) {
                continue;
            }

            if ($gift->product_imei_id) {
                $giftImei = ProductImei::query()
                    ->lockForUpdate()
                    ->find($gift->product_imei_id);

                if ($giftImei && ! $this->imeiBelongsToActiveSale($giftImei, $item->sale)) {
                    $giftImei->update([
                        'status' => ProductImei::STATUS_IN_STOCK,
                        'sold_at' => null,
                    ]);
                    app(ImeiStockSynchronizer::class)->sync($gift->product);
                }
            } else {
                Product::query()
                    ->whereKey($gift->product->id)
                    ->increment('stock', $giftBaseQuantity);
            }

            $this->decrementSoldCount($gift->product, $giftQuantity);
        }
    }

    private function restoreCancelledSaleImeis(Sale $sale): void
    {
        $sale->load(['items.productImei']);

        foreach ($sale->items as $item) {
            if (! $item->productImei) {
                continue;
            }

            $imei = ProductImei::query()
                ->lockForUpdate()
                ->find($item->productImei->id);

            if (! $imei || $this->imeiBelongsToActiveSale($imei, $sale)) {
                continue;
            }

            $imei->update([
                'status' => ProductImei::STATUS_IN_STOCK,
                'sold_at' => null,
            ]);

            app(ImeiStockSynchronizer::class)->sync($imei->product_id);
        }

        foreach ($sale->items->flatMap(fn (SaleItem $item) => $item->gifts) as $gift) {
            if (! $gift->product_imei_id) {
                continue;
            }

            $imei = ProductImei::query()->lockForUpdate()->find($gift->product_imei_id);
            if (! $imei || $this->imeiBelongsToActiveSale($imei, $sale)) {
                continue;
            }

            $imei->update(['status' => ProductImei::STATUS_IN_STOCK, 'sold_at' => null]);
            app(ImeiStockSynchronizer::class)->sync($imei->product_id);
        }
    }

    private function imeiBelongsToActiveSale(ProductImei $imei, Sale $currentSale): bool
    {
        return SaleItem::query()
            ->where('product_imei_id', $imei->id)
            ->where('sale_id', '!=', $currentSale->id)
            ->whereHas('sale', function ($query): void {
                $query->where('status', '!=', 'cancelled');
            })
            ->exists();
    }

    private function syncImeiStock(ProductImei $imei): void
    {
        app(ImeiStockSynchronizer::class)->sync($imei->product_id);
    }

    private function decrementSoldCount(Product $product, int $quantity): void
    {
        Product::query()
            ->whereKey($product->id)
            ->update([
                'sold_count' => DB::raw(
                    'CASE WHEN sold_count > ' . $quantity . ' THEN sold_count - ' . $quantity . ' ELSE 0 END'
                ),
            ]);
    }
}
