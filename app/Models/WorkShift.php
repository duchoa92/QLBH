<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    protected $fillable = ['user_id', 'opening_amount', 'opening_denominations', 'opening_bank_balances', 'cash_collected', 'customer_debt_amount', 'expected_cash', 'closing_amount', 'closing_denominations', 'closing_bank_balances', 'opened_at', 'closed_at'];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'opening_denominations' => 'array',
            'opening_bank_balances' => 'array',
            'cash_collected' => 'decimal:2',
            'customer_debt_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'closing_denominations' => 'array',
            'closing_bank_balances' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    /** Ca đang mở của một nhân viên (dùng chung cho POS, thanh toán QR và trả máy sửa). */
    public static function currentFor(?int $userId): ?self
    {
        if (! $userId) {
            return null;
        }

        return static::query()
            ->where('user_id', $userId)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
    }
}
