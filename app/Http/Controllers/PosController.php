<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\PosCheckoutService;


class PosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'Pos/Index'
        );
    }

    public function scanImei(
        Request $request
    ) {

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

            'product_id' =>
                $imei->product->id,

            'imei' => $imei->imei,

            'product' => [

                'id' => $imei->product->id,

                'name' => $imei->product->name,

                'price' => $imei->product->sell_price,

                'has_imei' => true,
            ],

            'product_id' => $imei->product->id,
        ]);
    }

 // Thanh toán
    public function checkout(
        Request $request,
        PosCheckoutService $service,
    )
    {
        $sale = $service->checkout(

            items: $request->items,

            customerId: $request->customer_id,

            paidAmount: $request->paid_amount,

            paymentMethod: $request->payment_method,

            userId: auth()->id(),
        );

        return response()->json([

            'success' => true,

            'sale_id' => $sale->id,
        ]);
    }
    // Xem hóa đơn bán hàng
    public function showSale(
        Sale $sale
    ): Response {

        $sale->load([

            'items.product',
        ]);

        return Inertia::render(

            'Pos/SaleInvoice',

            [
                'sale' => $sale,
            ]
        );
    }

}