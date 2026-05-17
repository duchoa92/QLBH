<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [

        'code',

        'user_id',

        'subtotal',

        'discount',

        'total',

        'customer_paid',

        'change_money',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(
            SaleItem::class
        );
    }
}