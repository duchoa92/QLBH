<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankPaymentTransaction extends Model
{
    protected $fillable = ['transaction_id', 'bank_payment_intent_id', 'amount', 'description'];
    public function intent(): BelongsTo { return $this->belongsTo(BankPaymentIntent::class, 'bank_payment_intent_id'); }
}
