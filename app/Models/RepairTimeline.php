<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairTimeline extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [

        'repair_id',

        'user_id',

        'status',

        'title',

        'description',
        'issue',
        'parts_needed',
        'waiting_for_parts',
        'expected_days',
    ];

    protected $casts = [
        'issue' => 'array',
        'expected_days' => 'integer',
        'waiting_for_parts' => 'boolean',
    ];

    /**
     * Repair
     */
    public function repair(): BelongsTo
    {
        return $this->belongsTo(
            Repair::class
        );
    }

    /**
     * User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function images(): HasMany
    {
        return $this->hasMany(RepairImage::class, 'timeline_id');
    }
}
