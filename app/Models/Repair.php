<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Customer;

class Repair extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [

        'code',
        'customer_id',

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
        'parts_total',
        'labor_cost',
        'surcharge',
        'final_cost',
        'paid_amount',
        'change_amount',
        'payment_method',
        'payment_note',

        'status',

        'intake_type',
        'warranty_source_type',
        'warranty_source_id',
        'warranty_expires_at',
        'warranty_status',
        'warranty_declined_at',
        'warranty_decline_reason',
        'warranty_covered_amount',

        'repair_warranty_days',
        'repair_warranty_started_at',
        'repair_warranty_expires_at',
        'repair_warranty_voided_at',
        'repair_warranty_void_reason',
        'repair_warranty_voided_by',

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
        'warranty_expires_at' => 'datetime',
        'warranty_declined_at' => 'datetime',
        'repair_warranty_started_at' => 'datetime',
        'repair_warranty_expires_at' => 'datetime',
        'repair_warranty_voided_at' => 'datetime',
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
        )->latest('created_at')->latest('id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(RepairPart::class);
    }

        /**
     * Khách hàng.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
        );
    }
    
}
