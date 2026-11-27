<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Cấu hình gửi email (SMTP) lưu trong bảng settings, áp dụng lúc khởi động ứng dụng.
 * Mật khẩu SMTP được mã hóa bằng APP_KEY, không bao giờ trả về trình duyệt.
 */
class MailSettings
{
    public const KEYS = [
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
    ];

    /** Ghi đè cấu hình mail của Laravel bằng cấu hình đã lưu (nếu có). */
    public static function apply(): void
    {
        $host = trim((string) setting('mail_host', ''));
        if ($host === '') {
            return;
        }

        $encryption = setting('mail_encryption', 'tls') === 'ssl' ? 'ssl' : 'tls';
        $username = trim((string) setting('mail_username', ''));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) setting('mail_port', 587) ?: 587,
            // SSL (cổng 465) dùng "smtps"; TLS (cổng 587) dùng "smtp" và tự nâng cấp STARTTLS.
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $username !== '' ? $username : null,
            'mail.mailers.smtp.password' => self::password(),
        ]);

        $from = trim((string) setting('mail_from_address', ''));
        if ($from !== '') {
            config([
                'mail.from.address' => $from,
                'mail.from.name' => trim((string) setting('mail_from_name', '')) ?: setting('shop_name', config('app.name')),
            ]);
        }
    }

    /** Hệ thống có cách gửi email thật hay chỉ ghi log (mặc định chưa cấu hình). */
    public static function isConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /** Dữ liệu cho form cài đặt (không có mật khẩu). */
    public static function forForm(): array
    {
        return [
            'host' => (string) setting('mail_host', ''),
            'port' => (string) setting('mail_port', '587'),
            'encryption' => setting('mail_encryption', 'tls') === 'ssl' ? 'ssl' : 'tls',
            'username' => (string) setting('mail_username', ''),
            'has_password' => (string) setting('mail_password', '') !== '',
            'from_address' => (string) setting('mail_from_address', ''),
            'from_name' => (string) setting('mail_from_name', ''),
            'configured' => trim((string) setting('mail_host', '')) !== '',
        ];
    }

    private static function password(): ?string
    {
        $value = (string) setting('mail_password', '');
        if ($value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable $exception) {
            return null;
        }
    }
}
