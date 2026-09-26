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
            'birthday' => $data['birthday'] ?? null,
            'gender' => $data['gender'] ?? null,
            'cccd' => $data['cccd'] ?? null,
            'province' => $data['province'] ?? null,
            'district' => $data['district'] ?? null,
            'ward' => $data['ward'] ?? null,
            'address' => $data['address'] ?? null,
            'note' => $data['note'] ?? null,
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