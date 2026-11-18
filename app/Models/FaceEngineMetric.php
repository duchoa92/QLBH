<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceEngineMetric extends Model
{
    protected $fillable = ['metric_date', 'engine', 'frames', 'faces', 'processing_ms'];

    protected function casts(): array
    {
        return ['metric_date' => 'date'];
    }
}
