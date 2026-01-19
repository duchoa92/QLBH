<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnlyFirstUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nếu CHƯA có user nào trong hệ thống
        if (User::count() === 0) {

            // Cho phép truy cập trang setup-admin
            if ($request->is('setup-admin') || $request->is('setup-admin/*')) {
                return $next($request);
            }

            // CẤM toàn bộ route khác (kể cả /login)
            return redirect()->route('setup.admin');
        }

        // Đã có user → cho đi tiếp bình thường
        return $next($request);
    }
}
