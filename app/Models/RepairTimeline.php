<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}