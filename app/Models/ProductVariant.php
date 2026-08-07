<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'attributes',
        'cost_price',
        'sell_price',
        'stock',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    // Quan hệ với Imeis
    public function imeis()
    {
        return $this->hasMany(ProductImei::class, 'variant_id');
    }
}
