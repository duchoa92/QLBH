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
        'avatar',
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

    /**
     * Không xóa tài khoản đã tạo giao dịch: một số khóa ngoại cũ dùng cascade.
     * Chặn tại đây để lịch sử bán hàng/ca làm việc không bị mất theo tài khoản.
     */
    public function deletionUsage(): array
    {
        return [
            'sales' => \DB::table('sales')->where('user_id', $this->id)->count(),
            'work_shifts' => \DB::table('work_shifts')->where('user_id', $this->id)->count(),
            'bank_payments' => \DB::table('bank_payment_intents')->where('user_id', $this->id)->count(),
        ];
    }

    public function canBeDeleted(): bool
    {
        return collect($this->deletionUsage())->sum() === 0;
    }
}
