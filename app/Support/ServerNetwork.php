<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Process\Process;

/**
 * Đổi IP máy chủ Ubuntu từ trang Cài đặt.
 *
 * Website không có quyền root. Nó chỉ (1) gọi bản script thuộc root ở /usr/local/sbin ở chế độ chỉ-đọc để quét mạng,
 * (2) ghi file yêu cầu rồi khởi động dịch vụ systemd `qlbh-ip-change` (sudoers chỉ cho phép đúng lệnh này).
 * Dịch vụ tự kiểm tra lại mọi giá trị, đổi IP, cập nhật APP_URL/chứng chỉ rồi ghi kết quả vào STATUS_FILE.
 * Cài đặt phần này bằng: sudo bash scripts/install-network-helper.sh (install-ubuntu.sh và update.sh tự chạy).
 */
class ServerNetwork
{
    public const HELPER = '/usr/local/sbin/qlbh-set-static-ip';

    public const STATUS_FILE = '/var/lib/qlbh/network-change.json';

    public const REQUEST_FILE = '/var/lib/qlbh/inbox/ip-change.req';

    public const SERVICE = 'qlbh-ip-change.service';

    /** @return array{supported: bool, message: string} */
    public static function support(): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return ['supported' => false, 'message' => 'Chỉ đổi được IP khi QLBH chạy trên máy chủ Ubuntu. Trên Windows hãy đổi IP trong cài đặt mạng của Windows.'];
        }
        if (! is_executable(self::HELPER) || ! is_dir(dirname(self::REQUEST_FILE)) || ! is_writable(dirname(self::REQUEST_FILE))) {
            return ['supported' => false, 'message' => 'Máy chủ chưa cài trợ giúp đổi IP. Chạy một lần trên máy chủ: sudo bash scripts/install-network-helper.sh (hoặc scripts/update.sh).'];
        }

        return ['supported' => true, 'message' => ''];
    }

    /** Quét mạng (khoảng 5-15 giây). Trả về mảng đã decode từ JSON của script. */
    public static function scan(): array
    {
        return self::runJson([self::HELPER, '--json'], 90);
    }

    /** Kiểm tra nhanh một IP (hợp lệ, không trùng router, không có thiết bị khác dùng). */
    public static function check(string $ip): array
    {
        return self::runJson([self::HELPER, '--check', $ip], 30);
    }

    /** Ghi yêu cầu và khởi động dịch vụ đổi IP. Trả về true nếu đã gửi được yêu cầu. */
    public static function request(string $ip, ?string $gateway, ?string $dns): bool
    {
        $lines = ['ip='.$ip];
        if ($gateway) {
            $lines[] = 'gateway='.$gateway;
        }
        if ($dns) {
            $lines[] = 'dns='.$dns;
        }
        $tmp = self::REQUEST_FILE.'.'.bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, implode("\n", $lines)."\n") === false || ! @rename($tmp, self::REQUEST_FILE)) {
            @unlink($tmp);

            return false;
        }
        $process = new Process(['sudo', '-n', self::systemctl(), 'start', '--no-block', self::SERVICE]);
        $process->setTimeout(15);
        $process->run();

        return $process->isSuccessful();
    }

    /** Kết quả lần đổi IP gần nhất: state = running|ok|failed hoặc null nếu chưa có lần nào. */
    public static function status(): ?array
    {
        $raw = @file_get_contents(self::STATUS_FILE);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }

    private static function systemctl(): string
    {
        foreach (['/usr/bin/systemctl', '/bin/systemctl'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'systemctl';
    }

    private static function runJson(array $command, int $timeout): array
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        try {
            $process->run();
        } catch (\Throwable $e) {
            return ['error' => 'Không quét được mạng: '.$e->getMessage()];
        }
        $data = json_decode(trim($process->getOutput()), true);

        return is_array($data) ? $data : ['error' => 'Không đọc được kết quả quét mạng. '.trim($process->getErrorOutput())];
    }
}
