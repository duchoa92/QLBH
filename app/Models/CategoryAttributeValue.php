<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryAttributeValue extends Model
{
    protected $fillable = [
        'attribute_id',
        'value'
    ];

    // thuộc về attribute
    public function attribute()
    {
        return $this->belongsTo(CategoryAttribute::class, 'attribute_id');
    }
}