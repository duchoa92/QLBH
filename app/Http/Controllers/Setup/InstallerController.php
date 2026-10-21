<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use RuntimeException;
use Throwable;

class InstallerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setup/Index');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate($this->databaseRules());

        try {
            $this->probe($data);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'database' => 'Không thể kết nối: '.$exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Đã kết nối được máy chủ. Database sẽ được tạo nếu chưa tồn tại khi bạn khởi tạo website.');
    }

    public function install(Request $request): RedirectResponse
    {
        $data = $request->validate($this->databaseRules() + [
            'admin_username' => ['required', 'string', 'max:50', 'alpha_dash'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_phone' => ['required', 'string', 'max:30', function ($attribute, $value, $fail) {
                $digits = preg_replace('/\D+/', '', $value);
                if (! preg_match('/^\+?[0-9\s().-]+$/D', $value) || strlen($digits) < 8 || strlen($digits) > 15) {
                    $fail('Nhập số điện thoại hợp lệ từ 8 đến 15 chữ số.');
                }
            }],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_writable($envPath)) {
            return back()->withErrors(['database' => 'Máy chủ không cho phép ghi file .env. Hãy cấp quyền ghi cho file này rồi thử lại.']);
        }

        try {
            $this->createDatabaseIfMissing($data);
            $connection = $this->configureConnection($data);
            $this->writeEnvironment($data);
            DB::purge($connection);

            $tables = Schema::connection($connection)->getTableListing();
            $hasMigrations = Schema::connection($connection)->hasTable('migrations');
            if ($tables && ! $hasMigrations) {
                throw new RuntimeException('Database đã có bảng dữ liệu và chưa có lịch sử migrations. Hãy chọn một database trống.');
            }
            if ($hasMigrations && Schema::connection($connection)->hasTable('users') && User::on($connection)->exists()) {
                throw new RuntimeException('Database đã có tài khoản người dùng. Trình cài đặt không thể ghi đè dữ liệu hiện có.');
            }

            $exitCode = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
            if ($exitCode !== 0) {
                throw new RuntimeException(trim(Artisan::output()) ?: 'Không thể tạo cấu trúc database.');
            }

            $exitCode = Artisan::call('db:seed', [
                '--class' => RolePermissionSeeder::class,
                '--force' => true,
                '--no-interaction' => true,
            ]);
            if ($exitCode !== 0) {
                throw new RuntimeException(trim(Artisan::output()) ?: 'Không thể khởi tạo quyền quản trị.');
            }

            $admin = User::create([
                'name' => $data['admin_name'],
                'username' => $data['admin_username'],
                'phone' => preg_replace('/\D+/', '', $data['admin_phone']),
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
            ]);
            $admin->assignRole('Super Admin');

            File::ensureDirectoryExists(storage_path('app'));
            if (file_put_contents(storage_path('app/installed'), now()->toIso8601String().PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Không thể tạo khóa hoàn tất cài đặt trong storage/app.');
            }

            Auth::login($admin);
            $request->session()->regenerate();
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors([
                'database' => 'Cài đặt chưa hoàn tất: '.$exception->getMessage(),
            ]);
        }

        return redirect($admin->landingPath())->with('success', 'Thiết lập hoàn tất. Chào mừng bạn đến với hệ thống!');
    }

    private function databaseRules(): array
    {
        return [
            'database_driver' => ['required', Rule::in(['mysql', 'pgsql', 'sqlite'])],
            'database_host' => ['nullable', 'string', 'max:255'],
            'database_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'database_username' => ['nullable', 'string', 'max:255'],
            'database_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function probe(array $data): void
    {
        if ($data['database_driver'] === 'sqlite') {
            $path = $this->sqlitePath($data['database_name']);
            if (! is_writable(database_path()) || (is_file($path) && ! is_writable($path))) {
                throw new RuntimeException('Thư mục hoặc file SQLite không cho phép ghi.');
            }
            if (is_file($path) && filesize($path) > 0) {
                $this->assertDatabaseEmpty(new PDO('sqlite:'.$path), 'sqlite', true);
            }
            return;
        }

        $pdo = $this->serverPdo($data);
        if ($data['database_driver'] === 'mysql') {
            $statement = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $statement->execute([$data['database_name']]);
            $exists = (bool) $statement->fetchColumn();
        } else {
            $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
            $statement->execute([$data['database_name']]);
            $exists = (bool) $statement->fetchColumn();
        }

        if (! $exists) {
            return;
        }

        $target = $this->targetPdo($data);
        $this->assertDatabaseEmpty($target, $data['database_driver']);
    }

    private function createDatabaseIfMissing(array $data): void
    {
        $driver = $data['database_driver'];
        if ($driver === 'sqlite') {
            $path = $this->sqlitePath($data['database_name']);
            if (! is_file($path) && ! touch($path)) {
                throw new RuntimeException('Không thể tạo file SQLite trong thư mục database.');
            }
            if (filesize($path) > 0) {
                $this->assertDatabaseEmpty(new PDO('sqlite:'.$path), 'sqlite', true);
            }
            return;
        }

        $pdo = $this->serverPdo($data);
        $exists = false;
        if ($driver === 'mysql') {
            $statement = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $statement->execute([$data['database_name']]);
            $exists = (bool) $statement->fetchColumn();
            if (! $exists) {
                $name = '`'.str_replace('`', '``', $data['database_name']).'`';
                $pdo->exec("CREATE DATABASE {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        } else {
            $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
            $statement->execute([$data['database_name']]);
            $exists = (bool) $statement->fetchColumn();
            if (! $exists) {
                $name = '"'.str_replace('"', '""', $data['database_name']).'"';
                $pdo->exec("CREATE DATABASE {$name} WITH ENCODING 'UTF8'");
            }
        }

        if ($exists) {
            $this->assertDatabaseEmpty($this->targetPdo($data), $driver);
        }
    }

    private function serverPdo(array $data): PDO
    {
        $driver = $data['database_driver'];
        $host = $data['database_host'] ?: '127.0.0.1';
        $port = $data['database_port'] ?: ($driver === 'mysql' ? 3306 : 5432);
        $dsn = $driver === 'mysql'
            ? "mysql:host={$host};port={$port};charset=utf8mb4"
            : "pgsql:host={$host};port={$port};dbname=postgres";

        return new PDO($dsn, $data['database_username'] ?? '', $data['database_password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    private function targetPdo(array $data): PDO
    {
        $driver = $data['database_driver'];
        if ($driver === 'sqlite') {
            $path = $this->sqlitePath($data['database_name']);
            return is_file($path) ? new PDO('sqlite:'.$path) : new PDO('sqlite::memory:');
        }
        $host = $data['database_host'] ?: '127.0.0.1';
        $port = $data['database_port'] ?: ($driver === 'mysql' ? 3306 : 5432);
        $dsn = $driver === 'mysql'
            ? "mysql:host={$host};port={$port};dbname={$data['database_name']};charset=utf8mb4"
            : "pgsql:host={$host};port={$port};dbname={$data['database_name']}";

        return new PDO($dsn, $data['database_username'] ?? '', $data['database_password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function assertDatabaseEmpty(PDO $pdo, string $driver, bool $sqlite = false): void
    {
        if ($driver === 'mysql') {
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($driver === 'pgsql') {
            $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        }

        if ($tables && in_array('migrations', $tables, true)) {
            if (! in_array('users', $tables, true) || (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
                return;
            }
        }

        if ($tables) {
            throw new RuntimeException('Database đã có bảng. Hãy dùng một database trống để tránh ảnh hưởng dữ liệu hiện có.');
        }
    }

    private function configureConnection(array $data): string
    {
        $driver = $data['database_driver'];
        if ($driver === 'sqlite') {
            $connection = config('database.connections.sqlite');
            $connection['database'] = $this->sqlitePath($data['database_name']);
            config(['database.connections.sqlite' => $connection, 'database.default' => 'sqlite']);
            return 'sqlite';
        }

        $connection = config('database.connections.'.$driver);
        $connection['host'] = $data['database_host'] ?: '127.0.0.1';
        $connection['port'] = $data['database_port'] ?: ($driver === 'mysql' ? 3306 : 5432);
        $connection['database'] = $data['database_name'];
        $connection['username'] = $data['database_username'] ?? '';
        $connection['password'] = $data['database_password'] ?? '';
        config(['database.connections.'.$driver => $connection, 'database.default' => $driver]);
        return $driver;
    }

    private function sqlitePath(string $name): string
    {
        $name = preg_replace('/\.sqlite$/i', '', basename($name));
        return database_path($name.'.sqlite');
    }

    private function writeEnvironment(array $data): void
    {
        $path = base_path('.env');
        $contents = file_get_contents($path);
        $values = [
            'DB_CONNECTION' => $data['database_driver'],
            'DB_HOST' => $data['database_driver'] === 'sqlite' ? '' : ($data['database_host'] ?: '127.0.0.1'),
            'DB_PORT' => $data['database_driver'] === 'sqlite' ? '' : (string) ($data['database_port'] ?: ($data['database_driver'] === 'mysql' ? 3306 : 5432)),
            'DB_DATABASE' => $data['database_driver'] === 'sqlite' ? $this->sqlitePath($data['database_name']) : $data['database_name'],
            'DB_USERNAME' => $data['database_username'] ?? '',
            'DB_PASSWORD' => $data['database_password'] ?? '',
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ];

        foreach ($values as $key => $value) {
            $line = $key.'="'.addcslashes((string) $value, "\\\"").'"';
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            if (preg_match($pattern, $contents)) {
                $contents = preg_replace_callback($pattern, static fn () => $line, $contents, 1);
            } else {
                $contents .= PHP_EOL.$line.PHP_EOL;
            }
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Không thể lưu cấu hình vào .env.');
        }
    }
}
