<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $customers = Customer::query()
            ->select([
                'id',
                'code',
                'full_name',
                'phone',
                'point_balance',
                'debt_balance',
                'customer_type',
            ])
            ->where(function ($query) use ($q) {
                $query->where('phone', 'like', "%{$q}%")
                      ->orWhere('full_name', 'like', "%{$q}%")
                      ->orWhere('code', 'like', "%{$q}%")
                      ->orWhere('cccd', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json($customers);
    }
}