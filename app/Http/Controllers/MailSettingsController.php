<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class MailSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:100'],
        ], [
            'host.required' => 'Nhập máy chủ SMTP.',
            'port.required' => 'Nhập cổng.',
            'port.integer' => 'Cổng không hợp lệ.',
            'from_address.required' => 'Nhập email gửi đi.',
            'from_address.email' => 'Email gửi đi không hợp lệ.',
        ]);

        Setting::set('mail_host', trim($data['host']));
        Setting::set('mail_port', (string) $data['port']);
        Setting::set('mail_encryption', $data['encryption']);
        Setting::set('mail_username', trim((string) ($data['username'] ?? '')));
        Setting::set('mail_from_address', trim($data['from_address']));
        Setting::set('mail_from_name', trim((string) ($data['from_name'] ?? '')));
        // Để trống mật khẩu = giữ mật khẩu đã lưu.
        if (filled($data['password'] ?? null)) {
            Setting::set('mail_password', Crypt::encryptString($data['password']));
        }

        return back()->with('success', 'Đã lưu cấu hình email.');
    }

    public function destroy(): RedirectResponse
    {
        foreach (MailSettings::KEYS as $key) {
            Setting::forget($key);
        }

        return back()->with('success', 'Đã xóa cấu hình email.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email']], [
            'to.required' => 'Nhập email nhận thư thử.',
            'to.email' => 'Email nhận không hợp lệ.',
        ]);

        MailSettings::apply();
        Mail::purge();

        if (! MailSettings::isConfigured()) {
            return back()->with('error', 'Chưa có cấu hình email. Hãy lưu cấu hình trước khi gửi thử.');
        }

        try {
            Mail::raw('Đây là thư thử từ hệ thống. Nếu bạn đọc được thư này, email gửi đi đã hoạt động.', function ($message) use ($data) {
                $message->to($data['to'])->subject('Thư thử từ hệ thống');
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Gửi thử không thành công: '.$exception->getMessage());
        }

        return back()->with('success', 'Đã gửi thư thử tới '.$data['to'].'. Hãy kiểm tra hộp thư.');
    }
}
