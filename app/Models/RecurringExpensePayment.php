<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpensePayment extends Model
{
    protected $fillable = ['recurring_expense_id', 'period', 'paid_date', 'amount', 'expense_id', 'user_id'];

    protected function casts(): array
    {
        return ['paid_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
