<?php

namespace App\Http\Controllers;

use App\Models\ProductImei;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        if ($imei->status === 'sold') {

            return response()->json([
                'message' => 'IMEI đã bán',
            ], 422);
        }

        return response()->json([

            'id' => $imei->id,

            'imei' => $imei->imei,

            'product' => [

                'id' => $imei->product->id,

                'name' => $imei->product->name,

                'price' => $imei->product->sell_price,
            ],
        ]);
    }
}