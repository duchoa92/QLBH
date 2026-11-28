<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Đổi IP, khởi động lại và tắt máy chủ từ trang Cài đặt (Ubuntu và Windows).
 *
 * Website KHÔNG có quyền quản trị. Nó chỉ (1) gọi bản script thuộc quản trị ở chế độ chỉ-đọc để quét mạng,
 * (2) ghi một file yêu cầu nhỏ vào thư mục "inbox", rồi (3) một dịch vụ nền có quyền cao đọc file, tự kiểm tra lại
 * mọi giá trị và thực hiện. Kết quả ghi vào STATUS_FILE.
 *  - Ubuntu : dịch vụ systemd qlbh-ip-change / qlbh-reboot / qlbh-poweroff (sudoers chỉ cho phép đúng các lệnh start này).
 *             Cài bằng: sudo bash scripts/install-network-helper.sh (install-ubuntu.sh và update.sh tự chạy).
 *  - Windows: tác vụ "QLBH Helper" (SYSTEM) theo dõi thư mục %ProgramData%\QLBH\inbox.
 *             Cài bằng: scripts\install-windows-helper.ps1 (install-windows.ps1 tự chạy).
 */
class ServerNetwork
{
    public const HELPER = '/usr/local/sbin/qlbh-set-static-ip';

    private const LINUX_DIR = '/var/lib/qlbh';

    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    private static function windowsDir(): string
    {
        return rtrim((string) (getenv('ProgramData') ?: 'C:\\ProgramData'), '\\/').'\\QLBH';
    }

    public static function statusFile(): string
    {
        return self::isWindows() ? self::windowsDir().'\\network-change.json' : self::LINUX_DIR.'/network-change.json';
    }

    private static function inbox(): string
    {
        return self::isWindows() ? self::windowsDir().'\\inbox' : self::LINUX_DIR.'/inbox';
    }

    private static function windowsScript(): string
    {
        return self::windowsDir().'\\bin\\windows-network.ps1';
    }

    /** @return array{supported: bool, message: string} */
    public static function support(): array
    {
        if (self::isWindows()) {
            if (! is_file(self::windowsScript()) || ! is_dir(self::inbox()) || ! is_writable(self::inbox())) {
                return ['supported' => false, 'message' => 'Máy chủ chưa cài trợ giúp. Mở PowerShell "Run as administrator" tại thư mục QLBH và chạy một lần: powershell -ExecutionPolicy Bypass -File scripts\\install-windows-helper.ps1'];
            }

            return ['supported' => true, 'message' => ''];
        }
        if (PHP_OS_FAMILY !== 'Linux') {
            return ['supported' => false, 'message' => 'Hệ điều hành này chưa được hỗ trợ.'];
        }
        if (! is_executable(self::HELPER) || ! is_dir(self::inbox()) || ! is_writable(self::inbox())) {
            return ['supported' => false, 'message' => 'Máy chủ chưa cài trợ giúp. Chạy một lần trên máy chủ: sudo bash scripts/install-network-helper.sh (hoặc scripts/update.sh).'];
        }

        return ['supported' => true, 'message' => ''];
    }

    private static function helperCommand(array $args): array
    {
        if (self::isWindows()) {
            $map = ['--json' => '-Json', '--check' => '-Check', '--status' => '-Status'];

            return array_merge(['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', self::windowsScript()], array_map(fn ($a) => $map[$a] ?? $a, $args));
        }

        return array_merge([self::HELPER], $args);
    }

    /** Quét mạng (khoảng 5-15 giây). Trả về mảng đã decode từ JSON của script. */
    public static function scan(): array
    {
        return self::runJson(self::helperCommand(['--json']), 90);
    }

    /** Kiểm tra nhanh một IP (hợp lệ, không trùng router, không có thiết bị khác dùng). */
    public static function check(string $ip): array
    {
        return self::runJson(self::helperCommand(['--check', $ip]), 40);
    }

    /** Ghi yêu cầu đổi IP và đánh thức dịch vụ nền. Trả về true nếu đã gửi được yêu cầu. */
    public static function request(string $ip, ?string $gateway, ?string $dns): bool
    {
        $lines = ['ip='.$ip];
        if ($gateway) {
            $lines[] = 'gateway='.$gateway;
        }
        if ($dns) {
            $lines[] = 'dns='.$dns;
        }

        return self::drop('ip-change.req', implode("\n", $lines)."\n", 'qlbh-ip-change.service');
    }

    /** Khởi động lại hoặc tắt máy chủ: action = restart | shutdown. */
    public static function power(string $action): bool
    {
        if (! in_array($action, ['restart', 'shutdown'], true)) {
            return false;
        }

        return self::drop('power.req', $action."\n", $action === 'restart' ? 'qlbh-reboot.service' : 'qlbh-poweroff.service');
    }

    private static function drop(string $name, string $content, string $linuxService): bool
    {
        $target = self::inbox().(self::isWindows() ? '\\' : '/').$name;
        $tmp = $target.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (@file_put_contents($tmp, $content) === false || ! @rename($tmp, $target)) {
            @unlink($tmp);

            return false;
        }
        if (self::isWindows()) {
            return true; // tác vụ nền "QLBH Helper" tự phát hiện file trong vài giây
        }
        $process = new Process(['sudo', '-n', self::systemctl(), 'start', '--no-block', $linuxService]);
        $process->setTimeout(15);
        $process->run();

        return $process->isSuccessful();
    }

    /** Kết quả lần đổi IP gần nhất: state = running|ok|failed hoặc null nếu chưa có lần nào. */
    public static function status(): ?array
    {
        $raw = @file_get_contents(self::statusFile());
        if (is_string($raw)) {
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Cảnh báo ngắn về mạng cho nút tác vụ (null nếu ổn hoặc chưa cài trợ giúp). Lưu cache 10 phút để không tốn tài nguyên mỗi trang.
     *
     * @return array{level: string, title: string, message: string}|null
     */
    public static function health(): ?array
    {
        if (! self::support()['supported']) {
            return null;
        }

        return Cache::remember('server.network.health', 600, function (): ?array {
            $data = self::runJson(self::helperCommand(['--status']), 20);
            if (isset($data['error']) || empty($data['current_ip'])) {
                return null;
            }
            if (! ($data['is_static'] ?? true)) {
                return ['level' => 'warning', 'title' => 'IP máy chủ chưa cố định', 'message' => 'Đang nhận IP tự động ('.$data['current_ip'].'), có thể đổi khi khởi động lại router. Vào Cài đặt → Mạng & máy chủ để cố định.'];
            }
            $host = (string) ($data['app_url_host'] ?? '');
            if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host) && $host !== $data['current_ip']) {
                return ['level' => 'warning', 'title' => 'IP máy chủ lệch cấu hình', 'message' => 'APP_URL đang là '.$host.' nhưng IP máy là '.$data['current_ip'].'. Vào Cài đặt → Mạng & máy chủ, chọn giữ/đổi IP để cập nhật.'];
            }

            return null;
        });
    }

    public static function forgetHealth(): void
    {
        Cache::forget('server.network.health');
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
        $out = preg_replace('/^\xEF\xBB\xBF/', '', trim($process->getOutput()));
        $data = json_decode((string) $out, true);

        return is_array($data) ? $data : ['error' => 'Không đọc được kết quả quét mạng. '.trim($process->getErrorOutput())];
    }
}
