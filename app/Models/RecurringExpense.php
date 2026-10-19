<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringExpense extends Model
{
    protected $fillable = ['category', 'description', 'amount', 'due_day', 'remind_days', 'payment_method', 'recipient', 'note', 'is_active', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RecurringExpensePayment::class);
    }
}
