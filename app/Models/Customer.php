<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Searchable;
use Illuminate\Validation\ValidationException;

class Customer extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = [
        'code',
        'full_name',
        'phone',
        'email',
        'birthday',
        'gender',
        'cccd',
        'province',
        'district',
        'ward',
        'address',
        'note',
        'point_balance',
        'debt_balance',
        'total_spent',
        'total_orders',
        'last_order_at',
        'customer_type',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'birthday' => 'date',
        'last_order_at' => 'datetime',
        'is_active' => 'boolean',
        'point_balance' => 'decimal:2',
        'debt_balance' => 'decimal:2',
        'total_spent' => 'decimal:2',
    ];

    public static function normalizeCccd(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\\D+/', '', $value) ?? '';
        return $digits === '' ? null : $digits;
    }

    public function setCccdAttribute(?string $value): void
    {
        $this->attributes['cccd'] = self::normalizeCccd($value);
    }

    /*
    |--------------------------------------------------
    | RELATIONS
    |--------------------------------------------------
    */

    public function images(): HasMany
    {
        return $this->hasMany(CustomerImage::class);
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(CustomerImage::class)
            ->where('is_primary', true);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(CustomerPoint::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(CustomerDebt::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CustomerLog::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    /*
    |--------------------------------------------------
    | PHÁT SINH & CHẶN GIAO DỊCH
    |--------------------------------------------------
    */

    /** Thống kê các phát sinh liên quan khách hàng; total > 0 nghĩa là không được xóa. */
    public function usageSummary(): array
    {
        $sales = $this->sales()->count();
        $repairs = $this->repairs()->count();
        $debts = $this->debts()->count();
        $points = $this->points()->count();
        $hasBalance = abs((float) $this->debt_balance) > 0 || abs((float) $this->point_balance) > 0;

        return [
            'sales' => $sales,
            'repairs' => $repairs,
            'debts' => $debts,
            'points' => $points,
            'total' => $sales + $repairs + $debts + $points + ($hasBalance ? 1 : 0),
        ];
    }

    public function isBlocked(): bool
    {
        return $this->is_active === false;
    }

    /** Chặn khách bị khóa phát sinh giao dịch mới (bán hàng, tiếp nhận sửa chữa). */
    public function assertCanTransact(): void
    {
        if ($this->isBlocked()) {
            throw ValidationException::withMessages([
                'customer_id' => "Khách hàng {$this->full_name} ({$this->code}) đang bị chặn giao dịch. Hãy bỏ chặn trong danh sách khách hàng để tiếp tục.",
            ]);
        }
    }

    public function scopeNotBlocked(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('is_active', true)->orWhereNull('is_active'));
    }

    /*
    |--------------------------------------------------
    | SCOPES
    |--------------------------------------------------
    */

    public function scopeActive(
        Builder $query
    ): Builder
    {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeSearch(
        Builder $query,
        string $keyword
    )
    {
        $keyword = self::normalizeVietnamese(
            $keyword
        );

        return $query->where(function ($q) use ($keyword) {

            $q->where(
                'search_text',
                'like',
                "%{$keyword}%"
            )

            ->orWhere(
                'phone',
                'like',
                "%{$keyword}%"
            );

        });
    }

    protected static function booted(): void
    {
        static::saving(function (
            Customer $customer
        ): void {

            $customer->search_text =
                self::normalizeSearch(
                    $customer->full_name ?? ''
                );

        });
    }


}
