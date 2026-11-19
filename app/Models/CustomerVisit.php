<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerVisit extends Model
{
    protected $fillable = [
        'customer_id', 'recognized', 'descriptor', 'descriptor_engine', 'thumb', 'user_id', 'camera_id', 'first_seen_at', 'last_seen_at', 'served_at', 'served_by',
    ];

    protected function casts(): array
    {
        return [
            'recognized' => 'boolean',
            'descriptor' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
