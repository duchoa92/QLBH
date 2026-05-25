<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SaleItem;

class Sale extends Model
{
    protected $fillable = [

        'code',

        'customer_id',

        'user_id',

        'subtotal',

        'discount',

        'tax',

        'grand_total',

        'paid_amount',

        'change_amount',

        'payment_method',

        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(
            SaleItem::class
        );
    }

    // Khách hàng
    public function customer()
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    // Người dùng
    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

}