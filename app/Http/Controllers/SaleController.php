<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    // Danh sách hóa đơn bán hàng
    public function index(): Response
    {
        $sales = Sale::query()
            ->with([
                'customer', // Nạp mối quan hệ khách hàng[cite: 6]
                'user',     // Nạp thông tin thu ngân[cite: 6]
                'items.product.conversionUnit',
                'items.variant',
                'items.productImei',
                'items.gifts.product',
            ])
            ->when(
                request('search'),
                function ($query) {
                    $search = request('search');
                    $query->where(function ($q) use ($search) {
                        $q->where('code', 'like', '%' . $search . '%')
                          ->orWhereHas('customer', function ($customerQuery) use ($search) {
                              $customerQuery->where('full_name', 'like', '%' . $search . '%')
                                           ->orWhere('phone', 'like', '%' . $search . '%');
                          })
                          ->orWhereHas('items', function ($itemQuery) use ($search) {
                              $itemQuery->whereHas('productImei', function ($imeiQuery) use ($search) {
                                  $imeiQuery->where('imei', 'like', '%' . $search . '%');
                              });
                          });
                    });
                }
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'Sales/Index',
            [
                'sales' => $sales,
                'filters' => [
                    'search' => request('search'),
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
