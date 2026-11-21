<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class CustomerDebt extends Model
{

    protected $fillable = [
        'customer_id',
        'type',
        'amount',
        'source_type',
        'source_id',
        'note',
        'payment_method',
        'user_id',
        'payment_group', 'received_amount', 'change_amount', 'shortage_amount', 'debt_before', 'debt_after',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
