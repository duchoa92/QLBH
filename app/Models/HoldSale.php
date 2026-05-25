<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoldSale extends Model
{
    protected $fillable = [

        'user_id',

        'customer_id',

        'name',

        'cart_items',

        'grand_total',
    ];

    protected $casts = [

        'cart_items' => 'array',
    ];
}