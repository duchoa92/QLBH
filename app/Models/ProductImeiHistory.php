<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImeiHistory extends Model
{
    protected $fillable = [
        'product_imei_id',
        'imei',
        'type',
        'reference_type',
        'reference_id',
        'cost_price',
        'sell_price',
        'meta',
        'note',
        'happened_at',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'meta' => 'array',
        'happened_at' => 'datetime',
    ];

    public function productImei(): BelongsTo
    {
        return $this->belongsTo(ProductImei::class);
    }
}
