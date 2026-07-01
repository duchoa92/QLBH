<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'color',
        'storage',
        'version',
        'cost_price',
        'sell_price',
        'stock',
    ];

    // Quan hệ với Imeis
    public function imeis()
    {
        return $this->hasMany(ProductImei::class, 'variant_id');
    }
}
