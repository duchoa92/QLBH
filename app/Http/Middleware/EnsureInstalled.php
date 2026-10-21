<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $alreadyInstalled = defined('QLBH_DATABASE_INSTALLED')
            ? QLBH_DATABASE_INSTALLED
            : is_file(storage_path('app/installed'));
        $isSetupRoute = $request->is('setup', 'setup/*');

        if (! $alreadyInstalled && $request->hasSession()) {
            $request->session()->forget(Auth::guard()->getName());
        }

        if (! $alreadyInstalled && ! $isSetupRoute && ! $request->is('up')) {
            return redirect('/setup');
        }
        if ($alreadyInstalled && $isSetupRoute) {
            return redirect('/');
        }

        return $next($request);
    }
}
