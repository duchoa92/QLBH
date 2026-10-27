<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRecipients
{
    public static function withPermission(string $permission): Collection
    {
        return User::query()->get()
            ->filter(fn (User $user): bool => $user->can($permission))
            ->values();
    }
}
