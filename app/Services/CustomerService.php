<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Str;

class CustomerService
{
    public function create(array $data): Customer
    {
        return Customer::create([
            'code' => $this->generateCode(),
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'point_balance' => 0,
            'debt_balance' => 0,
            'total_spent' => 0,
            'total_orders' => 0,
            'customer_type' => 'retail',
            'is_active' => true,
        ]);
    }

    private function generateCode(): string
    {
        return 'KH' . date('Ymd') . strtoupper(Str::random(4));
    }
}