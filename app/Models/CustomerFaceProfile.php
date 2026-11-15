<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFaceProfile extends Model
{
    protected $fillable = ['customer_id', 'descriptor', 'source', 'consented_at', 'consented_by'];

    protected function casts(): array
    {
        return ['descriptor' => 'array', 'consented_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
