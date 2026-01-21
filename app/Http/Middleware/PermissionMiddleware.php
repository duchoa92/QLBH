<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        // Chưa đăng nhập
        if (!$user) {
            abort(401, 'Chưa đăng nhập');
        }

        // Không có quyền
        if (!$user->hasPermission($permission)) {
            abort(403, 'Bạn không có quyền thực hiện hành động này');
        }

        return $next($request);
    }
}
