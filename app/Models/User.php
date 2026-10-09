<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    
    use HasRoles;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function landingPath(): string
    {
        $destinations = [
            'dashboard.view' => '/dashboard',
            'repairs.view' => '/repairs',
            'pos.access' => '/pos',
            'stock_imports.view' => '/stock-import',
            'products.view' => '/products',
            'customers.view' => '/customers',
            'sales.view' => '/sales',
            'suppliers.view' => '/suppliers',
            'users.view' => '/users',
            'settings.view' => '/settings',
        ];

        foreach ($destinations as $permission => $path) {
            if ($this->can($permission)) {
                return $path;
            }
        }

        return route('profile.edit', [], false);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'phone',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
