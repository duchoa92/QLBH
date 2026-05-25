<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerSearchController extends Controller
{
    public function __invoke(
        Request $request
    ): JsonResponse {

        $keyword = $request->get('q');

        $customers = Customer::query()

            ->when(
                $keyword,
                function ($query) use ($keyword) {

                    $query

                        ->where(
                            'name',
                            'like',
                            "%{$keyword}%"
                        )

                        ->orWhere(
                            'phone',
                            'like',
                            "%{$keyword}%"
                        );
                }
            )

            ->limit(10)

            ->get([
                'id',
                'name',
                'phone',
            ]);

        return response()->json(
            $customers
        );
    }
}