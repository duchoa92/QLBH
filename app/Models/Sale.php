<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\SaleItem;

class Sale extends Model
{
    protected $fillable = [

        'code',
        'customer_id',
        'user_id',
        'work_shift_id',
        'subtotal',
        'discount',
        'invoice_discount',
        'tax',
        'extra_fee',
        'grand_total',
        'paid_amount',
        'cash_received_amount',
        'bank_received_amount',
        'change_amount',
        'payment_method',
        'bank_account',
        'status',
        'cancel_reason', 
        'cancelled_at',
        'note',

    ];

    public function items(): HasMany
    {
        return $this->hasMany(
            SaleItem::class
        );
    }

    public function customerDebtEntries(): MorphMany
    {
        return $this->morphMany(CustomerDebt::class, 'source');
    }

    /**
     * Gắn `old_debt_paid`: số tiền thừa của hóa đơn này đã dùng để trả nợ cũ.
     * Dùng chung cho trang Hóa đơn, phiếu in và API (trước đây lặp ở 3 nơi).
     */
    public function withOldDebtPaid(): static
    {
        return $this->setAttribute('old_debt_paid', (float) $this->customerDebtEntries()
            ->where('type', 'decrease')
            ->where('note', 'like', 'Trích tiền thừa hóa đơn%')
            ->sum('amount'));
    }

    // Khách hàng
    public function customer()
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    // Người dùng
    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

}
