<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
/*     public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    } */


public function store(Request $request)
{
    $request->validate([
        'login' => 'required|string', //Không quan tâm là email hay gì
        'password' => 'required|string',
    ]);

    $login = $request->login;
      // Xác định cột đăng nhập
    $field = filter_var($login, FILTER_VALIDATE_EMAIL) //Là email → dùng cột email
        ? 'email'
        : (preg_match('/^[0-9]+$/', $login) ? 'phone' : 'username'); //Là số → dùng cột phone. Còn lại → username
    //Kiểm tra đúng user
    if (!Auth::attempt([ 
        $field => $login,
        'password' => $request->password,
        'status' => 1, //Chỉ user đang hoạt động (status = 1) mới login được
    ])) {
        return back()->withErrors([
            'login' => 'Thông tin đăng nhập không đúng',
        ]);
    }

    $request->session()->regenerate(); //✔ Chống session fixation. BẮT BUỘC trong hệ thống thật

    return redirect()->intended('/dashboard');
}

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
