<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerImage extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'path',
        'mime_type',
        'size',
        'is_primary',
        'uploaded_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}