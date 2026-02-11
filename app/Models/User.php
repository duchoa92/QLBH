<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'phone',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * User có nhiều role
     */
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles'
        );
    }

    /**
     * LẤY TOÀN BỘ PERMISSION CỦA USER (QUA ROLE)
     * 👉 trả về Collection
     */
    public function allPermissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id')
            ->values();
    }

    /**
     * CHECK USER CÓ PERMISSION CỤ THỂ KHÔNG
     */
    public function hasPermission(string $permission): bool
    {
        return $this->allPermissions()
            ->where('name', $permission)
            ->isNotEmpty();
    }
    
    /**
 * Lấy toàn bộ permission của user (qua role)
 */
    public function permissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique('id')
            ->values();
    }

}
