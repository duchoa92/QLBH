<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairImage extends Model
{
    protected $fillable = [

        'repair_id',

        'image',
    ];

    public function repair(): BelongsTo
    {
        return $this->belongsTo(
            Repair::class
        );
    }
}