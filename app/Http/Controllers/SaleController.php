<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductVariant;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
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

        $query = Sale::query()
            ->with([
                'customer',
                'user',
                'items.product.conversionUnit',
                'items.variant',
                'items.productImei',
                'items.gifts.product',
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
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'Sales/Index',
            [
                'sales' => $sales,
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
                return;
            }

            // Cập nhật thông tin hủy hóa đơn
            $sale->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ]);

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

    private function restoreSaleItemStock(SaleItem $item): void
    {
        $quantity = max(1, (int) $item->quantity);
        $baseQuantity = max(1, (int) ($item->base_quantity ?: $quantity));

        if ($item->productImei) {
            $imei = ProductImei::query()
                ->lockForUpdate()
                ->find($item->productImei->id);

            if ($imei) {
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

            Product::query()
                ->whereKey($gift->product->id)
                ->increment('stock', $giftBaseQuantity);

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

            $this->syncImeiStock($imei);
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
        if ($imei->variant_id) {
            $variantStock = ProductImei::query()
                ->where('product_id', $imei->product_id)
                ->where('variant_id', $imei->variant_id)
                ->where('status', ProductImei::STATUS_IN_STOCK)
                ->count();

            ProductVariant::query()
                ->whereKey($imei->variant_id)
                ->update(['stock' => $variantStock]);
        }

        $productStock = ProductImei::query()
            ->where('product_id', $imei->product_id)
            ->where('status', ProductImei::STATUS_IN_STOCK)
            ->count();

        Product::query()
            ->whereKey($imei->product_id)
            ->update(['stock' => $productStock]);
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
