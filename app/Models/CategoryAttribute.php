<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryAttribute extends Model
{
    protected $fillable = [
        'category_id',
        'name'
    ];
    protected $casts = [
        'options' => 'array',
    ];

    // thuộc về category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // có nhiều value
    public function values()
    {
        return $this->hasMany(CategoryAttributeValue::class, 'attribute_id');
    }
}