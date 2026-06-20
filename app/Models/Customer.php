<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Searchable;

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