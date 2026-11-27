<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Kiểm tra và tự chuẩn bị môi trường máy chủ cho trang /setup (Ubuntu/Linux, Windows/Laragon).
 *
 * Chỉ làm những việc tiến trình web được phép làm (tạo thư mục, chmod file do chính nó sở hữu, storage:link, crontab của user web).
 * Việc cần quyền root (apt, nginx, chown) nằm ở scripts/install-ubuntu.sh; ở đây chỉ in ra lệnh để người dùng chạy.
 */
class ServerEnvironment
{
    public const MIN_PHP = '8.2.0';

    private const REQUIRED_EXTENSIONS = [
        'pdo' => 'PDO',
        'mbstring' => 'Mbstring',
        'openssl' => 'OpenSSL',
        'tokenizer' => 'Tokenizer',
        'xml' => 'XML',
        'ctype' => 'Ctype',
        'json' => 'JSON',
        'fileinfo' => 'Fileinfo',
        'curl' => 'cURL',
        'zip' => 'Zip (xuất/nhập Excel, sao lưu)',
    ];

    private const RECOMMENDED_EXTENSIONS = [
        'gd' => 'GD (xử lý ảnh)',
    ];

    public static function isLinux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    public static function report(): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, self::MIN_PHP, '>=');
        $checks[] = self::check('php', 'PHP '.PHP_VERSION, $phpOk, 'error', $phpOk ? '' : 'Cần PHP '.self::MIN_PHP.' trở lên.', self::isLinux() ? 'sudo add-apt-repository ppa:ondrej/php && sudo apt install php8.4-cli php8.4-fpm (hoặc chạy scripts/install-ubuntu.sh)' : '');

        foreach (self::REQUIRED_EXTENSIONS as $extension => $label) {
            $loaded = extension_loaded($extension);
            $checks[] = self::check('ext_'.$extension, 'Extension '.$label, $loaded, 'error', $loaded ? '' : "Thiếu extension {$extension}.", $loaded ? '' : self::installExtensionHint($extension));
        }
        foreach (self::RECOMMENDED_EXTENSIONS as $extension => $label) {
            $loaded = extension_loaded($extension);
            $checks[] = self::check('ext_'.$extension, 'Extension '.$label, $loaded, 'warning', $loaded ? '' : "Nên cài extension {$extension}.", $loaded ? '' : self::installExtensionHint($extension));
        }

        $envPath = base_path('.env');
        $envOk = is_file($envPath) ? is_writable($envPath) : is_writable(base_path());
        $checks[] = self::check('env', is_file($envPath) ? 'File .env ghi được' : 'Tạo được file .env', $envOk, 'error', $envOk ? '' : 'Tiến trình web không ghi được .env.', self::permissionHint(['.env']));

        $mustWrite = [
            'storage' => storage_path(),
            'storage/logs' => storage_path('logs'),
            'storage/framework' => storage_path('framework'),
            'storage/app' => storage_path('app'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
        foreach ($mustWrite as $label => $path) {
            $ok = is_dir($path) ? is_writable($path) : is_writable(dirname($path));
            $checks[] = self::check('dir_'.$label, "Thư mục {$label} ghi được", $ok, 'error', $ok ? '' : (is_dir($path) ? 'Không có quyền ghi.' : 'Chưa tồn tại và không tạo được.'), self::permissionHint(['storage', 'bootstrap/cache']));
        }

        $dbOk = is_writable(database_path());
        $checks[] = self::check('dir_database', 'Thư mục database ghi được (chỉ cần khi dùng SQLite)', $dbOk, 'warning', $dbOk ? '' : 'Không có quyền ghi.', self::permissionHint(['database']));

        $linked = file_exists(public_path('storage'));
        $checks[] = self::check('storage_link', 'Liên kết public/storage', $linked, 'warning', $linked ? '' : 'Chưa có. Nút "Tự cấu hình" sẽ tạo.', 'php artisan storage:link');

        return [
            'os' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'web_user' => self::webUser(),
            'checks' => $checks,
            'drivers' => [
                'mysql' => extension_loaded('pdo_mysql'),
                'pgsql' => extension_loaded('pdo_pgsql'),
                'sqlite' => extension_loaded('pdo_sqlite'),
            ],
            'cron' => self::cronStatus(),
            'ready' => array_filter($checks, fn ($item) => ! $item['ok'] && $item['level'] === 'error') === [],
        ];
    }

    /** Tự sửa những gì tiến trình web làm được. Trả về danh sách bước: [label, ok, message]. */
    public static function prepare(bool $installCron = false): array
    {
        $steps = [];

        try {
            $created = self::ensureEnvFile();
            $steps[] = self::step('Chuẩn bị file .env', true, $created ? 'Đã tạo .env từ .env.example.' : 'File .env đã sẵn sàng.');
        } catch (Throwable $exception) {
            $steps[] = self::step('Chuẩn bị file .env', false, $exception->getMessage());
        }

        $directories = [
            storage_path('app/public'), storage_path('framework/cache/data'), storage_path('framework/sessions'),
            storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache'),
        ];
        $failed = [];
        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
            if (is_dir($directory) && ! is_writable($directory)) {
                @chmod($directory, 0775);
            }
            if (! is_dir($directory) || ! is_writable($directory)) {
                $failed[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $directory);
            }
        }
        $steps[] = $failed
            ? self::step('Thư mục lưu trữ', false, 'Chưa ghi được: '.implode(', ', $failed).'. Chạy: '.self::permissionHint(['storage', 'bootstrap/cache']))
            : self::step('Thư mục lưu trữ', true, 'storage và bootstrap/cache đã ghi được.');

        if (file_exists(public_path('storage'))) {
            $steps[] = self::step('Liên kết public/storage', true, 'Đã có sẵn.');
        } else {
            try {
                Artisan::call('storage:link', ['--no-interaction' => true]);
                $steps[] = self::step('Liên kết public/storage', file_exists(public_path('storage')), trim(Artisan::output()) ?: 'Đã tạo.');
            } catch (Throwable $exception) {
                $steps[] = self::step('Liên kết public/storage', false, $exception->getMessage().' Chạy thủ công: php artisan storage:link');
            }
        }

        if ($installCron && self::isLinux()) {
            [$ok, $message] = self::installCron();
            $steps[] = self::step('Lịch chạy nền (cron)', $ok, $message);
        }

        return $steps;
    }

    /** Tạo .env từ .env.example nếu chưa có. Trả về true nếu vừa tạo mới. */
    public static function ensureEnvFile(): bool
    {
        $envPath = base_path('.env');
        if (is_file($envPath)) {
            if (! is_writable($envPath)) {
                throw new \RuntimeException('File .env không ghi được. '.self::permissionHint(['.env']));
            }
            return false;
        }

        if (! is_writable(base_path())) {
            throw new \RuntimeException('Không có quyền tạo .env trong thư mục dự án. '.self::permissionHint(['.']));
        }
        $example = base_path('.env.example');
        $contents = is_file($example) ? file_get_contents($example) : '';
        if (file_put_contents($envPath, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Không thể tạo file .env.');
        }
        @chmod($envPath, 0640);

        return true;
    }

    public static function cronLine(): string
    {
        $php = (new PhpExecutableFinder())->find(false) ?: '/usr/bin/php';

        return sprintf('* * * * * cd %s && %s artisan schedule:run >> /dev/null 2>&1', escapeshellarg(base_path()), escapeshellarg($php));
    }

    public static function cronStatus(): array
    {
        $status = ['supported' => false, 'installed' => null, 'line' => self::cronLine(), 'reason' => ''];
        if (! self::isLinux()) {
            $status['reason'] = 'Chỉ áp dụng cho Linux. Trên Windows dùng scripts/install-windows-scheduler.ps1.';
            return $status;
        }

        try {
            $process = new Process(['crontab', '-l'], null, null, null, 5);
            $process->run();
        } catch (Throwable $exception) {
            $status['reason'] = 'Máy chủ không cho PHP chạy lệnh crontab (proc_open bị tắt hoặc chưa cài cron).';
            return $status;
        }

        $status['supported'] = true;
        $output = $process->isSuccessful() ? $process->getOutput() : '';
        $status['installed'] = str_contains($output, base_path()) && str_contains($output, 'schedule:run');

        return $status;
    }

    /** @return array{0: bool, 1: string} */
    public static function installCron(): array
    {
        $status = self::cronStatus();
        if (! $status['supported']) {
            return [false, ($status['reason'] ?: 'Không dùng được crontab.').' Thêm thủ công bằng "sudo crontab -u '.self::webUser().' -e" với dòng: '.$status['line']];
        }
        if ($status['installed']) {
            return [true, 'Cron đã được cài từ trước.'];
        }

        try {
            $list = new Process(['crontab', '-l'], null, null, null, 5);
            $list->run();
            $current = $list->isSuccessful() ? rtrim($list->getOutput())."\n" : '';
            $write = new Process(['crontab', '-'], null, null, $current.$status['line']."\n", 5);
            $write->run();
            if (! $write->isSuccessful()) {
                throw new \RuntimeException(trim($write->getErrorOutput()) ?: 'crontab trả về lỗi.');
            }
        } catch (Throwable $exception) {
            return [false, 'Không ghi được crontab ('.$exception->getMessage().'). Thêm thủ công bằng "sudo crontab -u '.self::webUser().' -e" với dòng: '.$status['line']];
        }

        return [true, 'Đã cài cron cho user '.self::webUser().' (chạy schedule:run mỗi phút).'];
    }

    public static function webUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (! empty($info['name'])) {
                return $info['name'];
            }
        }

        return get_current_user() ?: 'www-data';
    }

    private static function permissionHint(array $paths): string
    {
        if (! self::isLinux()) {
            return '';
        }
        $user = self::webUser();
        $list = implode(' ', $paths);

        return 'cd '.base_path().' && sudo chown -R '.$user.':'.$user.' '.$list.' && sudo chmod -R u+rwX,g+rwX '.$list;
    }

    private static function installExtensionHint(string $extension): string
    {
        if (! self::isLinux()) {
            return '';
        }
        $short = ['pdo' => 'mysql', 'tokenizer' => 'common', 'ctype' => 'common', 'json' => 'common', 'fileinfo' => 'common', 'openssl' => 'common'][$extension] ?? $extension;
        $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

        return "sudo apt install php{$version}-{$short} && sudo systemctl restart php{$version}-fpm";
    }

    private static function check(string $key, string $label, bool $ok, string $level, string $detail = '', string $fix = ''): array
    {
        return compact('key', 'label', 'ok', 'level', 'detail', 'fix');
    }

    private static function step(string $label, bool $ok, string $message): array
    {
        return compact('label', 'ok', 'message');
    }
}
