<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A consented enrollment photo, stored privately outside the public disk. */
class CustomerFaceSample extends Model
{
    protected $fillable = [
        'customer_id', 'image_path', 'source', 'consented_at', 'consented_by',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
