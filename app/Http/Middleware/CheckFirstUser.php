<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class CheckFirstUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // CHƯA có user nào → chỉ cho vào setup-admin
        if (User::count() === 0) {

            if (!$request->is('setup-admin')) {
                return redirect()->route('setup.admin');
            }

            return $next($request);
        }

        // ĐÃ có user → cho request đi tiếp bình thường
        return $next($request);
    }
}
