<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class OnlyFirstUser
{
    public function handle(Request $request, Closure $next)
    {
        // Nếu đã tồn tại user → cấm vào setup
        if (User::count() > 0) {
            abort(403);
        }

        return $next($request);
    }
}
