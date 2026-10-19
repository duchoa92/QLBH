<?php

namespace App\Http\Controllers;

use App\Models\ProductImei;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Sale;
use App\Models\WorkShift;
use App\Services\PosCheckoutService;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

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
            ->with([
                'product.unit:id,name,short_name',
            ])
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
            ProductImei::STATUS_IN_STOCK
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
                    'unit_id' => $imei->product->unit_id,
                    'unit_name' => $imei->product->unit?->short_name ?: $imei->product->unit?->name,
                ],
            ]);
    }

    // Thanh toán
    public function checkout(
        Request $request,
        PosCheckoutService $service,
    )
    {
        $workShift = WorkShift::query()
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        if (! $workShift) {
            return response()->json(['success' => false, 'message' => 'Vui lòng mở ca làm trước khi bán hàng.'], 422);
        }

        // 1. THÊM VALIDATE: Kiểm tra dữ liệu đầu vào chặt chẽ
        $request->validate([

            'items' => 'required|array',

            'payment_method' => 'required|string',
            'bank_account' => 'required_if:payment_method,bank|nullable|string|max:50',
            'invoice_code' => ['nullable', 'string', 'regex:/^INV-[0-9]{14}$/', 'unique:sales,code'],

            'items.*.discount_type' => 'nullable|string',

            'items.*.discount_value' => 'nullable|numeric',

            'items.*.variant_id' => 'nullable|integer',
            
            'items.*.imei_id' => 'nullable|integer|exists:product_imeis,id',

            'items.*.warranty_days' => 'nullable|integer|min:0|max:3650',

            'items.*.warranty_value' => 'nullable|integer|min:0|max:3650',

            'items.*.warranty_unit' => 'nullable|in:day,month',

            'items.*.gift_product_id' => 'nullable|integer',

            'items.*.gifts' => 'nullable|array',

            'items.*.gifts.*.id' => 'required|integer|exists:products,id',

            'items.*.gifts.*.quantity' => 'nullable|integer|min:1',

            'items.*.gifts.*.imei_id' => 'nullable|integer|exists:product_imeis,id',

            'items.*.unit_id' => 'nullable|integer|exists:units,id',

            'items.*.unit_name' => 'nullable|string|max:100',

            'items.*.conversion_factor' => 'nullable|integer|min:1',

        ],
        [
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
                invoiceCode: $request->input('invoice_code'),
            );

            $sale->update([
                'work_shift_id' => $workShift->id,
                'bank_account' => $request->payment_method === 'bank' ? $request->bank_account : null,
            ]);

            $sale->load([
                'items.product.conversionUnit',
                'items.variant',
                'items.productImei',
                'items.gifts.product',
                'customer',
            ]);

            Notification::send(
                User::query()->get(),
                new SystemNotification(
                    'Đơn hàng mới',
                    'Hóa đơn ' . $sale->code . ' đã được tạo cho ' . ($sale->customer?->full_name ?? 'khách lẻ') . '.',
                    'sale',
                    route('sales.index')
                )
            );

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
                            'unit_name' => $item->unit_name,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                            'imei' => $item->productImei?->imei,
                            'warranty_days' => $item->warranty_days,
                            'warranty_value' => $item->warranty_value,
                            'warranty_unit' => $item->warranty_unit,
                            'warranty_expires_at' => $item->productImei?->customer_warranty_expires_at,
                            'variant' => $item->variant
                                ? [
                                    'id' => $item->variant->id,
                                    'attributes' => $item->variant->attributes,
                                ]
                                : null,
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
            'items.product.conversionUnit',
            'items.variant',
            'items.productImei',
            'items.gifts.product',
            'customer',
            'user'
        ]);

        return Inertia::render(
            'Pos/SaleInvoice',
            [
                'sale' => $sale,
            ]
        );
    }
}
