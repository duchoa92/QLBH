<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupCloudConnection extends Model
{
    protected $fillable = ['name', 'provider', 'configuration', 'enabled', 'last_tested_at'];

    protected $hidden = ['configuration'];

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:array',
            'enabled' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
