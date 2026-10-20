<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankPaymentIntent extends Model
{
    protected $fillable = [
        'invoice_code', 'user_id', 'work_shift_id', 'bank_account', 'expected_amount',
        'received_amount', 'checkout_payload', 'status', 'sale_id', 'sale_response', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['checkout_payload' => 'array', 'sale_response' => 'array', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function workShift(): BelongsTo { return $this->belongsTo(WorkShift::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function transactions(): HasMany { return $this->hasMany(BankPaymentTransaction::class); }
}
