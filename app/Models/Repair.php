<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repair extends Model
{
    protected $fillable = [

        'code',

        'customer_name',

        'customer_phone',

        'device_name',

        'imei',

        'issue',

        'repair_fee',

        'status',

        'note',

        'accessories',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(RepairImage::class);
    }
}