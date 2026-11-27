<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\ServerEnvironment;
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
        return Inertia::render('Setup/Index', [
            'environment' => ServerEnvironment::report(),
            'prepareResults' => session('setup_prepare'),
            'certificateUrl' => $this->certificateUrl(),
            // Chỉ gửi tên tài khoản; mật khẩu do script cài đặt đặt sẵn KHÔNG bao giờ gửi ra trình duyệt.
            'savedDatabase' => $this->savedDatabaseAccount() ? [
                'username' => $this->savedDatabaseAccount()['username'],
                'has_password' => true,
            ] : null,
        ]);
    }

    /** Tự chuẩn bị máy chủ (.env, thư mục, storage:link, cron trên Linux) rồi hiển thị lại kết quả. */
    public function prepare(Request $request): RedirectResponse
    {
        $data = $request->validate(['install_cron' => ['nullable', 'boolean']]);

        return back()->with('setup_prepare', ServerEnvironment::prepare((bool) ($data['install_cron'] ?? false)));
    }

    public function test(Request $request): RedirectResponse
    {
        $this->useSavedDatabasePassword($request);
        $data = $request->validate($this->databaseRules());

        try {
            $this->assertDriverAvailable($data['database_driver']);
            $this->probe($data);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'database' => 'Không thể kết nối: '.$this->describeDatabaseError($exception),
            ]);
        }

        return back()->with('status', 'Kết nối thành công.');
    }

    public function install(Request $request): RedirectResponse
    {
        // Chạy migrate có thể mất vài chục giây: không để PHP tự ngắt giữa chừng (sẽ làm database dở dang).
        @set_time_limit(0);
        ignore_user_abort(true);

        $this->useSavedDatabasePassword($request);
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
            'install_cron' => ['nullable', 'boolean'],
            // Cài đặt hệ thống ban đầu (sau này vẫn sửa được trong Cài đặt).
            'shop_name' => ['required', 'string', 'max:150'],
            'shop_phone' => ['nullable', 'string', 'max:50'],
            'shop_address' => ['nullable', 'string', 'max:255'],
            'app_locale' => ['required', 'in:vi,en'],
            'app_timezone' => ['required', 'in:Asia/Ho_Chi_Minh,Asia/Bangkok,UTC'],
            'date_format' => ['required', 'in:d/m/Y,Y-m-d,m/d/Y'],
            'currency_symbol' => ['required', 'string', 'max:12'],
            'currency_format' => ['required', 'in:vi-VN,en-US'],
        ]);

        try {
            $this->assertDriverAvailable($data['database_driver']);
            ServerEnvironment::ensureEnvFile();
        } catch (Throwable $exception) {
            return back()->withErrors(['database' => $exception->getMessage()]);
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

            // Quyền và vai trò (Super Admin, Admin) đã được các migration tạo sẵn: không cần seeder.
            $admin = User::create([
                'name' => $data['admin_name'],
                'username' => $data['admin_username'],
                'phone' => preg_replace('/\D+/', '', $data['admin_phone']),
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
            ]);
            $admin->assignRole('Super Admin');

            // Cài đặt hệ thống ban đầu: tên cửa hàng, ngôn ngữ, múi giờ, định dạng ngày và tiền tệ.
            foreach ([
                'shop_name' => $data['shop_name'],
                'print_shop_phone' => $data['shop_phone'] ?? null,
                'print_shop_address' => $data['shop_address'] ?? null,
                'app_locale' => $data['app_locale'],
                'app_timezone' => $data['app_timezone'],
                'date_format' => $data['date_format'],
                'currency_symbol' => $data['currency_symbol'],
                'currency_format' => $data['currency_format'],
            ] as $key => $value) {
                if ($value !== null && $value !== '') {
                    Setting::set($key, $value);
                }
            }

            File::ensureDirectoryExists(storage_path('app'));
            if (file_put_contents(storage_path('app/installed'), now()->toIso8601String().PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Không thể tạo khóa hoàn tất cài đặt trong storage/app.');
            }

            $this->clearSavedDatabaseAccount();

            try {
                Auth::logout();
            } catch (Throwable) {
                // Không có ai đăng nhập hoặc cookie ghi nhớ cũ: bỏ qua, session sẽ bị hủy ngay bên dưới.
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors([
                'database' => 'Cài đặt chưa hoàn tất: '.$this->describeDatabaseError($exception),
            ]);
        }

        $notes = null;
        if (! empty($data['install_cron']) && ServerEnvironment::isLinux()) {
            try {
                [$cronOk, $cronMessage] = ServerEnvironment::installCron();
                $notes = $cronOk ? $cronMessage : 'Chưa tự cài được lịch chạy nền: '.$cronMessage;
            } catch (Throwable $exception) {
                report($exception);
                $notes = 'Chưa tự cài được lịch chạy nền: '.$exception->getMessage();
            }
        }

        // Không render trang ngay trong request này: nó vừa đổi DB, ghi .env, chạy migrate và hủy session nên
        // dễ phát sinh lỗi 500. Chuyển sang một request mới (/setup/complete) để hiện màn hình thành công.
        // flash (chỉ tồn tại cho đúng 1 lần mở trang thành công): mật khẩu không bị lưu lại trong session.
        $isSqlite = $data['database_driver'] === 'sqlite';
        $request->session()->flash('setup_done', [
            'username' => $data['admin_username'],
            'password' => $data['admin_password'],
            'name' => $data['admin_name'] ?? null,
            'phone' => $data['admin_phone'] ?? null,
            'email' => $data['admin_email'] ?? null,
            'database' => [
                'driver' => $data['database_driver'],
                'host' => $isSqlite ? null : ($data['database_host'] ?? null),
                'port' => $isSqlite ? null : ($data['database_port'] ?? null),
                'name' => $data['database_name'],
                'file' => $isSqlite ? $this->sqlitePath($data['database_name']) : null,
                'username' => $isSqlite ? null : ($data['database_username'] ?? null),
                'password' => $isSqlite ? null : ($data['database_password'] ?? null),
            ],
            'shop' => [
                'name' => $data['shop_name'],
                'phone' => $data['shop_phone'] ?? null,
                'address' => $data['shop_address'] ?? null,
                'locale' => $data['app_locale'],
                'timezone' => $data['app_timezone'],
                'date_format' => $data['date_format'],
                'currency' => $data['currency_symbol'],
                'currency_format' => $data['currency_format'],
            ],
            'notes' => $notes,
            'system' => $this->systemSummary(),
        ]);

        return redirect('/setup/complete', 303);
    }

    /** Màn hình "Thiết lập thành công" (request mới, ứng dụng đã khởi động đầy đủ với database vừa cài). */
    public function complete(Request $request): Response|RedirectResponse
    {
        $done = $request->session()->get('setup_done');
        if (! is_array($done)) {
            return redirect()->route('login');
        }

        return Inertia::render('Setup/Index', ['done' => $done, 'certificateUrl' => $this->certificateUrl()]);
    }

    /** Tình trạng chạy nền / tự khởi động / ổ đĩa để hiện ở màn hình "Thiết lập thành công". */
    private function systemSummary(): array
    {
        try {
            $disk = \App\Support\DiskHealth::report();
        } catch (Throwable) {
            $disk = null;
        }

        return [
            'linux' => ServerEnvironment::isLinux(),
            'cron' => ServerEnvironment::cronStatus()['installed'] ?? null,
            'autostart' => ServerEnvironment::autostartStatus(),
            'disk' => $disk ? ['used_percent' => $disk['used_percent'], 'free_gb' => $disk['free_gb'], 'total_gb' => $disk['total_gb'], 'level' => $disk['level']] : null,
        ];
    }

    /** Đường dẫn tải chứng chỉ gốc (do script --https / windows-https.ps1 chép vào public/certs), nếu có. */
    private function certificateUrl(): ?string
    {
        return is_file(public_path('certs/rootCA.crt')) ? '/certs/rootCA.crt' : null;
    }

    /** Đọc SETUP_DB_USERNAME / SETUP_DB_PASSWORD do scripts/install-ubuntu.sh ghi vào .env (nếu có). */
    private function savedDatabaseAccount(): ?array
    {
        $path = base_path('.env');
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (! preg_match('/^(SETUP_DB_USERNAME|SETUP_DB_PASSWORD)=(.*)$/', $line, $m)) {
                continue;
            }
            $value = trim($m[2]);
            if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = stripcslashes(substr($value, 1, -1));
            }
            $values[$m[1]] = $value;
        }

        if (($values['SETUP_DB_USERNAME'] ?? '') === '' || ($values['SETUP_DB_PASSWORD'] ?? '') === '') {
            return null;
        }

        return ['username' => $values['SETUP_DB_USERNAME'], 'password' => $values['SETUP_DB_PASSWORD']];
    }

    /** Ô mật khẩu để trống và đúng tài khoản script đã tạo: dùng mật khẩu đã lưu ở máy chủ. */
    private function useSavedDatabasePassword(Request $request): void
    {
        if ($request->input('database_driver') === 'sqlite' || filled($request->input('database_password'))) {
            return;
        }

        $saved = $this->savedDatabaseAccount();
        if ($saved && $request->input('database_username') === $saved['username']) {
            $request->merge(['database_password' => $saved['password']]);
        }
    }

    /** Sau khi cài xong, xóa mật khẩu tạm khỏi .env (mật khẩu thật đã nằm ở DB_PASSWORD). */
    private function clearSavedDatabaseAccount(): void
    {
        $path = base_path('.env');
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return;
        }
        $cleaned = preg_replace('/^(SETUP_DB_USERNAME|SETUP_DB_PASSWORD)=.*\R?/m', '', $contents);
        if ($cleaned !== null && $cleaned !== $contents) {
            @file_put_contents($path, $cleaned, LOCK_EX);
        }
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

    /** Giữ nguyên lỗi gốc nhưng thêm gợi ý cách xử lý cho các lỗi kết nối phổ biến. */
    private function describeDatabaseError(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $linux = ServerEnvironment::isLinux();

        if (stripos($message, 'refused') !== false || str_contains($message, '[2002]') || str_contains($message, 'Connection refused')) {
            return $message.' → Máy chủ database chưa chạy hoặc sai Host/Cổng. '
                .($linux
                    ? 'Kiểm tra: sudo systemctl status mariadb (hoặc mysql), rồi sudo systemctl start mariadb.'
                    : 'Với Laragon: bấm "Start All" và chờ MySQL báo đang chạy; nếu MySQL dùng cổng khác (ví dụ 3307) hãy nhập đúng cổng đó.');
        }
        if (str_contains($message, '[1045]') || stripos($message, 'Access denied') !== false) {
            return $message.' → Sai tên người dùng hoặc mật khẩu database, hoặc user này chưa được phép đăng nhập từ máy này.';
        }
        if (str_contains($message, '[2002]') === false && stripos($message, 'getaddrinfo') !== false) {
            return $message.' → Không phân giải được tên Host. Thử nhập 127.0.0.1.';
        }

        return $message;
    }

    private function assertDriverAvailable(string $driver): void
    {
        if (! extension_loaded('pdo_'.$driver)) {
            $hint = ServerEnvironment::isLinux()
                ? ' Trên Ubuntu: sudo apt install php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'-'.($driver === 'sqlite' ? 'sqlite3' : $driver).' rồi khởi động lại php-fpm.'
                : '';
            throw new RuntimeException("PHP chưa bật extension pdo_{$driver}.".$hint);
        }
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
