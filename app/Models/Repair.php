<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repair extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [

        'code',

        'customer_name',
        'customer_phone',
        'contact_phone',

        'device_name',
        'imei',
        'serial',

        'screen_password',
        'screen_pattern',

        'account_type',
        'account_email',
        'account_password',

        'issue',
        'repair_request',
        'accessories',

        'estimated_cost',
        'final_cost',

        'status',

        'note',

        'technician_id',

        'received_at',
        'completed_at',
        'returned_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [

        'issue' => 'array',

        'accessories' => 'array',

        'received_at' => 'datetime',

        'completed_at' => 'datetime',

        'returned_at' => 'datetime',
    ];

    /**
     * Ảnh sửa chữa
     */
    public function images(): HasMany
    {
        return $this->hasMany(
            RepairImage::class
        );
    }

    /**
     * Kỹ thuật viên
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'technician_id'
        );
    }

    /**
     * Lịch sử sửa chữa
     */
    public function timelines(): HasMany
    {
        return $this->hasMany(
            RepairTimeline::class
        )->latest();
    }
    
}