<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'sentTo' => session('sent_to'),
        ]);
    }

    /**
     * Nhận tên đăng nhập, email hoặc số điện thoại (giống ô đăng nhập) rồi gửi liên kết
     * đặt lại mật khẩu tới email của tài khoản đó.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ], [
            'login.required' => 'Nhập tên đăng nhập, email hoặc số điện thoại.',
        ]);

        if (! MailSettings::isConfigured()) {
            throw ValidationException::withMessages([
                'login' => 'Hệ thống chưa cấu hình gửi email. Hãy nhờ quản trị viên cấu hình ở Thiết lập → Email gửi thư, hoặc đặt lại mật khẩu giúp bạn.',
            ]);
        }

        $user = $this->findUser(trim($data['login']));
        if (! $user) {
            throw ValidationException::withMessages(['login' => 'Không tìm thấy tài khoản phù hợp.']);
        }
        if (blank($user->email)) {
            throw ValidationException::withMessages(['login' => 'Tài khoản này chưa có email. Hãy nhờ quản trị viên đặt lại mật khẩu.']);
        }

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'login' => 'Không gửi được email. Hãy nhờ quản trị viên kiểm tra cấu hình email.',
            ]);
        }

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'password.reset-link-sent')->with('sent_to', $this->mask($user->email));
        }

        throw ValidationException::withMessages([
            'login' => $status === Password::RESET_THROTTLED
                ? 'Bạn vừa yêu cầu liên kết. Vui lòng thử lại sau một phút.'
                : 'Không gửi được liên kết cho tài khoản này.',
        ]);
    }

    private function findUser(string $login): ?User
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', $login)->first();
        }

        if (preg_match('/^\+?[0-9\s().-]+$/D', $login) && preg_match('/[0-9]/', $login)) {
            return User::query()->where('phone', preg_replace('/\D+/', '', $login))->first();
        }

        return User::query()->where('username', $login)->first();
    }

    /** a***@gmail.com: cho người dùng biết thư đi đâu mà không lộ trọn email. */
    private function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).str_repeat('*', max(2, min(5, mb_strlen($name) - 1))).'@'.$domain;
    }
}
