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
            ->get(['id', 'name', 'sku', 'sell_price', 'stock', 'product_type', 'manage_stock_by_serial', 'warranty_days'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->sell_price,
                'stock' => (int) $product->stock,
                'is_imei' => $product->product_type === 'imei' || (bool) $product->manage_stock_by_serial,
                'warranty_days' => (int) $product->warranty_days,
                'variants' => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $product->name . ' · ' . collect($variant->attributes ?? [])->filter()->join(' / '),
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'attributes' => $variant->attributes ?? [],
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

    public function searchAvailableImeis(Request $request, Product $product)
    {
        $query = ProductImei::query()
            ->where('product_id', $product->id)
            ->where('status', ProductImei::STATUS_IN_STOCK);

        if ($request->filled('sale_id')) {
            $saleId = (int) $request->query('sale_id');
            $query->orWhere(function ($imeis) use ($product, $saleId): void {
                $imeis->where('product_id', $product->id)
                    ->where(function ($sold) use ($saleId): void {
                        $sold->whereHas('saleItems', fn ($items) => $items->where('sale_id', $saleId))
                            ->orWhereHas('giftSaleItems', fn ($gifts) => $gifts->whereHas('saleItem', fn ($items) => $items->where('sale_id', $saleId)));
                    });
            });
        }

        return $query->with('variant:id,attributes')
            ->orderBy('imei')
            ->limit(100)
            ->get(['id', 'product_id', 'variant_id', 'imei', 'serial', 'status'])
            ->map(function (ProductImei $imei): array {
                $variantName = collect($imei->variant?->attributes ?? [])
                    ->map(function ($attribute): string {
                        if (is_array($attribute)) {
                            $attribute = $attribute['value'] ?? $attribute['label'] ?? $attribute['name'] ?? $attribute['title'] ?? $attribute['text'] ?? collect($attribute)->flatten()->filter()->join(' ');
                        }

                        return is_scalar($attribute) ? (string) $attribute : '';
                    })
                    ->filter()
                    ->join(' / ');

                return [
                    'id' => $imei->id,
                    'imei' => $imei->imei,
                    'serial' => $imei->serial,
                    'variant_id' => $imei->variant_id,
                    'variant_name' => $variantName,
                    'status' => $imei->status,
                ];
            });
    }

    public function edit(Sale $sale): Response
    {
        $sale->load(['customer', 'items.product.unit', 'items.variant', 'items.productImei', 'items.gifts.product', 'items.gifts.variant', 'items.gifts.productImei']);
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
            'items.*.imei_id' => ['nullable', 'integer', 'exists:product_imeis,id'],
            'items.*.warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'items.*.warranty_note' => ['nullable', 'string', 'max:1000'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percent,amount'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.gifts' => ['nullable', 'array'],
            'items.*.gifts.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.gifts.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.gifts.*.imei_id' => ['nullable', 'integer', 'exists:product_imeis,id'],
            'items.*.gifts.*.quantity' => ['required', 'integer', 'min:1'],
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'payment_method' => ['required', 'in:cash,bank,mixed,card'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'cash_received_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'bank_received_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ]);

        if ($data['payment_method'] === 'mixed') {
            $splitTotal = (float) ($data['cash_received_amount'] ?? 0) + (float) ($data['bank_received_amount'] ?? 0);
            if (abs($splitTotal - (float) $data['paid_amount']) >= 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages(['paid_amount' => 'Tiền mặt và chuyển khoản phải bằng tổng số tiền đã nhận.']);
            }
        }

        DB::transaction(function () use ($sale, $data): void {
            $sale = Sale::query()->lockForUpdate()->with(['items.product', 'items.variant', 'items.productImei', 'items.gifts'])->findOrFail($sale->id);
            $reason = $this->saleEditBlockedReason($sale);
            if ($reason) throw \Illuminate\Validation\ValidationException::withMessages(['sale' => $reason]);

            $oldDebt = max(0, (float) $sale->grand_total - (float) $sale->paid_amount);
            $oldCustomerId = $sale->customer_id;
            $oldImeiIds = collect();
            $imeiProductIds = collect();
            foreach ($sale->items as $oldItem) {
                if ($oldItem->product_imei_id) {
                    $oldImeiIds->push($oldItem->product_imei_id);
                    $imeiProductIds->push($oldItem->product_id);
                }
                foreach ($oldItem->gifts as $oldGift) {
                    if ($oldGift->product_imei_id) {
                        $oldImeiIds->push($oldGift->product_imei_id);
                        $imeiProductIds->push($oldGift->product_id);
                    }
                }
            }
            if ($oldImeiIds->isNotEmpty()) {
                ProductImei::query()->whereIn('id', $oldImeiIds->unique())->lockForUpdate()->get()->each(function (ProductImei $imei): void {
                    $imei->update([
                        'status' => ProductImei::STATUS_IN_STOCK,
                        'sold_at' => null,
                        'customer_warranty_days' => null,
                        'customer_warranty_expires_at' => null,
                        'customer_warranty_voided_at' => null,
                        'customer_warranty_void_reason' => null,
                        'customer_warranty_voided_by' => null,
                    ]);
                });
                $imeiProductIds->unique()->each(fn ($id) => app(ImeiStockSynchronizer::class)->sync((int) $id));
            }
            $paidAmount = round((float) $data['paid_amount'], 2);
            $subtotal = 0.0;
            $discount = 0.0;
            $newItems = [];

            foreach ($data['items'] as $row) {
                $product = Product::query()->lockForUpdate()->findOrFail($row['product_id']);
                $requiresImei = $product->product_type === 'imei' || (bool) $product->manage_stock_by_serial;
                $variant = null;
                if (!empty($row['variant_id'])) {
                    $variant = ProductVariant::query()->lockForUpdate()->where('product_id', $product->id)->where('is_active', true)->findOrFail($row['variant_id']);
                }
                $quantity = (int) $row['quantity'];
                $imei = null;
                if ($requiresImei) {
                    if ($quantity !== 1 || empty($row['imei_id'])) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Mỗi dòng IMEI phải có đúng một thiết bị được chọn.']);
                    $imei = ProductImei::query()->lockForUpdate()->where('product_id', $product->id)->where('status', ProductImei::STATUS_IN_STOCK)->findOrFail($row['imei_id']);
                    if ($variant && (int) $imei->variant_id !== (int) $variant->id) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'IMEI không thuộc biến thể đã chọn.']);
                    if (!$variant && $imei->variant_id) $variant = ProductVariant::query()->whereKey($imei->variant_id)->first();
                    if (collect($newItems)->contains(fn ($existing) => (int) ($existing['imei']?->id ?? 0) === (int) $imei->id)) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Không thể gán cùng một IMEI cho nhiều dòng.']);
                    $imeiProductIds->push($product->id);
                } elseif (!empty($row['imei_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Không được gán IMEI cho sản phẩm không quản lý IMEI.']);
                }
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
                $gifts = [];
                foreach ($row['gifts'] ?? [] as $giftRow) {
                    $giftProduct = Product::query()->lockForUpdate()->findOrFail($giftRow['product_id']);
                    $giftVariant = null;
                    if (!empty($giftRow['variant_id'])) $giftVariant = ProductVariant::query()->lockForUpdate()->where('product_id', $giftProduct->id)->where('is_active', true)->findOrFail($giftRow['variant_id']);
                    $giftRequiresImei = $giftProduct->product_type === 'imei' || (bool) $giftProduct->manage_stock_by_serial;
                    $giftImei = null;
                    $giftQuantity = (int) $giftRow['quantity'];
                    if ($giftRequiresImei) {
                        if ($giftQuantity !== 1 || empty($giftRow['imei_id'])) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Quà tặng IMEI cần chọn đúng một thiết bị.']);
                        $giftImei = ProductImei::query()->lockForUpdate()->where('product_id', $giftProduct->id)->where('status', ProductImei::STATUS_IN_STOCK)->findOrFail($giftRow['imei_id']);
                        if ($giftVariant && (int) $giftImei->variant_id !== (int) $giftVariant->id) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'IMEI quà tặng không thuộc biến thể đã chọn.']);
                        if (!$giftVariant && $giftImei->variant_id) $giftVariant = ProductVariant::query()->whereKey($giftImei->variant_id)->first();
                        if (collect($newItems)->contains(function ($existing) use ($giftImei) { return (int) ($existing['imei']?->id ?? 0) === (int) $giftImei->id || collect($existing['gifts'])->contains(fn ($gift) => (int) ($gift['imei']?->id ?? 0) === (int) $giftImei->id); }) || collect($gifts)->contains(fn ($existing) => (int) ($existing['imei']?->id ?? 0) === (int) $giftImei->id) || ($imei && (int) $imei->id === (int) $giftImei->id)) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Không thể gán cùng một IMEI cho nhiều dòng hoặc quà tặng.']);
                        $imeiProductIds->push($giftProduct->id);
                    } elseif (!empty($giftRow['imei_id'])) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'IMEI quà tặng không khớp với loại sản phẩm.']);
                    if (!$giftRequiresImei && $giftProduct->variants()->where('is_active', true)->exists() && !$giftVariant) throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Vui lòng chọn biến thể cho quà tặng ' . $giftProduct->name . '.']);
                    $gifts[] = ['product' => $giftProduct, 'variant' => $giftVariant, 'quantity' => $giftQuantity, 'imei' => $giftImei];
                }
                $warrantyDays = max(0, min(3650, (int) ($row['warranty_days'] ?? ($product->warranty_days ?? 0))));
                $warranty_note = filled($row['warranty_note'] ?? null) ? trim($row['warranty_note']) : null;
                $note = filled($row['note'] ?? null) ? trim($row['note']) : null;
                $newItems[] = compact('product', 'variant', 'quantity', 'price', 'type', 'value', 'lineDiscount', 'lineTotal', 'imei', 'gifts', 'warrantyDays', 'warranty_note', 'note');
            }

            $oldStockDelta = [];
            $oldSoldDelta = [];
            foreach ($sale->items as $item) {
                $oldSoldDelta[$item->product_id] = ($oldSoldDelta[$item->product_id] ?? 0) + (int) $item->quantity;
                if (!$item->product_imei_id) {
                    $key = ($item->variant_id ? 'v' . $item->variant_id : 'p' . $item->product_id);
                    $oldStockDelta[$key] = ($oldStockDelta[$key] ?? 0) + (int) ($item->base_quantity ?: $item->quantity);
                }
                foreach ($item->gifts as $gift) {
                    $oldSoldDelta[$gift->product_id] = ($oldSoldDelta[$gift->product_id] ?? 0) + (int) $gift->quantity;
                    if (!$gift->product_imei_id) {
                        $giftKey = $gift->variant_id ? 'v' . $gift->variant_id : 'p' . $gift->product_id;
                        $oldStockDelta[$giftKey] = ($oldStockDelta[$giftKey] ?? 0) + (int) ($gift->base_quantity ?: $gift->quantity);
                    }
                }
            }
            $newStockDelta = [];
            $newSoldDelta = [];
            foreach ($newItems as $item) {
                $newSoldDelta[$item['product']->id] = ($newSoldDelta[$item['product']->id] ?? 0) + $item['quantity'];
                if (!$item['imei']) {
                    $key = $item['variant'] ? 'v' . $item['variant']->id : 'p' . $item['product']->id;
                    $newStockDelta[$key] = ($newStockDelta[$key] ?? 0) + $item['quantity'];
                }
                foreach ($item['gifts'] as $gift) {
                    $newSoldDelta[$gift['product']->id] = ($newSoldDelta[$gift['product']->id] ?? 0) + $gift['quantity'];
                    if (!$gift['imei']) {
                        $giftKey = $gift['variant'] ? 'v' . $gift['variant']->id : 'p' . $gift['product']->id;
                        $newStockDelta[$giftKey] = ($newStockDelta[$giftKey] ?? 0) + $gift['quantity'];
                    }
                }
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

            $soldAt = now();
            foreach ($newItems as $item) {
                if ($item['imei']) $this->markEditedImeiSold($item['imei'], $soldAt, $item['warrantyDays']);
                foreach ($item['gifts'] as $gift) if ($gift['imei']) $this->markEditedImeiSold($gift['imei'], $soldAt, (int) ($gift['product']->warranty_days ?? 0));
            }
            $sale->items()->delete();
            foreach ($newItems as $item) {
                $saleItem = $sale->items()->create([
                    'product_id' => $item['product']->id,
                    'variant_id' => $item['variant']?->id,
                    'product_imei_id' => $item['imei']?->id,
                    'unit_id' => $item['product']->unit_id,
                    'unit_name' => $item['product']->unit?->short_name ?: $item['product']->unit?->name,
                    'quantity' => $item['quantity'],
                    'base_quantity' => $item['quantity'],
                    'conversion_factor' => 1,
                    'unit_price' => $item['price'],
                    'discount_type' => $item['type'],
                    'discount_value' => $item['value'],
                    'warranty_days' => $item['warrantyDays'],
                    'warranty_note' => $item['warranty_note'],
                    'note' => $item['note'],
                    'discount_amount' => $item['lineDiscount'],
                    'subtotal' => $item['lineTotal'] - $item['lineDiscount'],
                ]);
                foreach ($item['gifts'] as $gift) {
                    $saleItem->gifts()->create([
                        'product_id' => $gift['product']->id,
                        'variant_id' => $gift['variant']?->id,
                        'product_imei_id' => $gift['imei']?->id,
                        'quantity' => $gift['quantity'],
                        'base_quantity' => $gift['quantity'],
                        'conversion_factor' => 1,
                    ]);
                }
            }
            $imeiProductIds->unique()->each(fn ($id) => app(ImeiStockSynchronizer::class)->sync((int) $id));

            $newTotal = max(0, round($subtotal - $discount + (float) $sale->tax, 2));
            $newDebt = max(0, $newTotal - $paidAmount);
            $newCustomerId = $data['customer_id'] ?? null;
            if (!$newCustomerId && $paidAmount < $newTotal) {
                throw \Illuminate\Validation\ValidationException::withMessages(['customer_id' => 'Khách lẻ cần thanh toán đủ tiền; hãy chọn khách hàng để ghi nhận công nợ.']);
            }

            $cashReceived = match ($data['payment_method']) {
                'cash' => $paidAmount,
                'mixed' => round((float) ($data['cash_received_amount'] ?? 0), 2),
                default => 0,
            };
            $bankReceived = match ($data['payment_method']) {
                'bank' => $paidAmount,
                'mixed' => round((float) ($data['bank_received_amount'] ?? 0), 2),
                default => 0,
            };

            $sale->update([
                'customer_id' => $newCustomerId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'grand_total' => $newTotal,
                'paid_amount' => $paidAmount,
                'payment_method' => $data['payment_method'],
                'bank_account' => in_array($data['payment_method'], ['bank', 'mixed'], true) ? ($data['bank_account'] ?? null) : null,
                'cash_received_amount' => $cashReceived,
                'bank_received_amount' => $bankReceived,
                'change_amount' => max(0, $paidAmount - $newTotal),
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

    private function markEditedImeiSold(ProductImei $imei, \Illuminate\Support\Carbon $soldAt, int $warrantyDays): void
    {
        $warrantyDays = max(0, min(3650, $warrantyDays));
        $imei->update([
            'status' => ProductImei::STATUS_SOLD,
            'sold_at' => $soldAt,
            'customer_warranty_days' => $warrantyDays,
            'customer_warranty_expires_at' => $warrantyDays > 0 ? $soldAt->copy()->addDays($warrantyDays) : null,
            'customer_warranty_voided_at' => null,
            'customer_warranty_void_reason' => null,
            'customer_warranty_voided_by' => null,
        ]);
    }

    private function saleEditBlockedReason(Sale $sale): ?string
    {
        if ($sale->status !== 'completed') return 'Chỉ có thể sửa hóa đơn đang hoàn thành.';
        if (CustomerDebt::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->where('type', 'decrease')->exists()) return 'Hóa đơn đã có lịch sử thu công nợ nên không thể sửa.';
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
        if (!in_array($perPage, [10, 20, 50, 100], true)) $perPage = 10;

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
            ->withSum(['customerDebtEntries as old_debt_paid' => fn ($debtQuery) => $debtQuery
                ->where('type', 'decrease')
                ->where('note', 'like', 'Trích tiền thừa hóa đơn%')], 'amount')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Sales/Index',
            [
                'sales' => $sales,
                'selectedSale' => $request->filled('open_sale')
                    ? $this->withOldDebtPaid(Sale::query()->with([
                        'customer', 'user', 'items.product.conversionUnit', 'items.variant',
                        'items.productImei', 'items.gifts.product', 'items.gifts.productImei',
                    ])->findOrFail((int) $request->query('open_sale')))
                    : null,
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
        $this->withOldDebtPaid($sale);

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
        $this->withOldDebtPaid($sale);

        return Inertia::render(
            'Sales/Receipt',
            [
                'sale' => $sale,
            ]
        );
    }

    private function withOldDebtPaid(Sale $sale): Sale
    {
        $sale->setAttribute('old_debt_paid', (float) CustomerDebt::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', 'decrease')
            ->where('note', 'like', 'Trích tiền thừa hóa đơn%')
            ->sum('amount'));

        return $sale;
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
