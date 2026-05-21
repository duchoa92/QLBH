<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairImage extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [

        'repair_id',

        'image_path',
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
}