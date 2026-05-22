<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPoint extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'points',
        'source_type',
        'source_id',
        'note',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}