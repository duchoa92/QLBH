<?php

namespace App\Http\Controllers;

use App\Models\ProductImei;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Sale;
use App\Services\PosCheckoutService;

class PosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'Pos/Index'
        );
    }

    public function scanImei(Request $request) 
    {
        $request->validate([
            'imei' => [
                'required',
            ],
        ]);

        $imei = ProductImei::query()
            ->with('product')
            ->where(
                'imei',
                $request->imei
            )
            ->first();

        if (! $imei) {
            return response()->json([
                'message' => 'IMEI không tồn tại',
            ], 404);
        }

        if (
            $imei->status !==
            ProductImei::STATUS_AVAILABLE
        ) {
            return response()->json([
                'message' => 'IMEI không khả dụng',
            ], 422);
        }

        return response()->json([
            'id' => $imei->id,
            'product_id' => $imei->product->id,
            'imei' => $imei->imei,
            'product' => [
                'id' => $imei->product->id,
                'name' => $imei->product->name,
                'sell_price' => $imei->product->sell_price,
                'product_type' => $imei->product->product_type,
            ],
        ]);
    }

    // Thanh toán
    public function checkout(
        Request $request,
        PosCheckoutService $service,
    )
    {
        // 1. THÊM VALIDATE: Kiểm tra dữ liệu đầu vào chặt chẽ
        $request->validate([
            'items' => 'required|array',
            'payment_method' => 'required|string', // Bắt buộc phương thức thanh toán phải là chuỗi
        ], [
            'items.required' => 'Giỏ hàng không được để trống.',
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
        ]);

        try {

            $sale = $service->checkout(
                items: $request->items,
                customerId: $request->customer_id,
                paidAmount: $request->paid_amount ?? 0,
                paymentMethod: $request->payment_method ?? 'cash',
                payOldDebt: (bool) $request->pay_old_debt,
                userId: auth()->id(),
            );

            $sale->load([
                'items.product',
                'items.productImei',
                'customer',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $sale->id,
                    'code' => $sale->code,
                    'customer' => [
                        'full_name' => $sale->customer?->full_name ?? 'Khách lẻ',
                    ],
                    'items' => $sale->items->map(function ($item) {

                        return [
                            'id' => $item->id,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                            'imei' => $item->productImei?->imei,
                            'product' => [
                                'name' => $item->product->name,
                            ],
                        ];
                    }),
                    'subtotal' => $sale->subtotal,
                    'discount' => $sale->discount,
                    'paid' => (float) $sale->paid_amount,
                    'change' => (float) $sale->change_amount,
                    'payment_method' => $sale->payment_method ?? 'cash',
                ],
            ]);

        } catch (\Exception $e) {

            \Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // Xem hóa đơn bán hàng
    public function showSale(Sale $sale): Response 
    {
        $sale->load([
            'items.product',
            'items.productImei',
        ]);

        return Inertia::render(
            'Pos/SaleInvoice',
            [
                'sale' => $sale,
            ]
        );
    }
}