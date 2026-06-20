<?php

namespace App\Models;

use App\Support\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;
    use Searchable;

    protected $fillable = [

        'code',
        'name',
        'search_text',
        'phone',
        'email',
        'tax_code',
        'contact_person',
        'province',
        'district',
        'ward',
        'address',
        'debt_balance',
        'is_active',
        'note',
    ];

    protected $casts = [

        'is_active' => 'boolean',

        'debt_balance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(
            function (
                Supplier $supplier
            ): void {

                $supplier->search_text =
                    self::normalizeSearch(
                        $supplier->name ?? ''
                    );
            }
        );
    }
}