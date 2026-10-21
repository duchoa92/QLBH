<?php

$basePath = dirname(__DIR__);
$installedFlag = $basePath.'/storage/app/installed';
require $basePath.'/vendor/autoload.php';
Dotenv\Dotenv::createMutable($basePath)->safeLoad();
$readEnv = static fn (string $key, string $default = ''): string => (string) ($_ENV[$key] ?? $_SERVER[$key] ?? $default);

if (! is_file($installedFlag)) {
    $legacyInstalled = false;

    try {
        $driver = $readEnv('DB_CONNECTION', 'sqlite');
        $database = $readEnv('DB_DATABASE', 'database/database.sqlite');
        if ($driver === 'sqlite' && ! preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $database)) {
            $database = $basePath.'/'.ltrim($database, '/\\');
        }
        $username = $readEnv('DB_USERNAME');
        $password = $readEnv('DB_PASSWORD');
        $host = $readEnv('DB_HOST', '127.0.0.1');
        $port = $readEnv('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
        $dsn = match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
            'sqlite' => 'sqlite:'.$database,
            default => '',
        };
        if ($driver === 'sqlite' && $database !== ':memory:' && ! is_file($database)) {
            $dsn = '';
        }
        if ($dsn !== '') {
            $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
            if ($driver === 'mysql') {
                $hasUsers = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            } elseif ($driver === 'pgsql') {
                $hasUsers = (bool) $pdo->query("SELECT to_regclass('public.users')")->fetchColumn();
            } else {
                $hasUsers = (bool) $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
            }
            if ($hasUsers) {
                $legacyInstalled = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
            }
        }
    } catch (Throwable $exception) {
        // If the current connection is unavailable, the setup wizard can repair it.
    }

    if ($legacyInstalled) {
        if (! is_dir(dirname($installedFlag))) {
            mkdir(dirname($installedFlag), 0775, true);
        }
        @file_put_contents($installedFlag, "existing database detected\n", LOCK_EX);
        define('QLBH_DATABASE_INSTALLED', true);
    } else {
        $envPath = $basePath.'/.env';
        if (! is_file($envPath) && is_file($basePath.'/.env.example')) {
            copy($basePath.'/.env.example', $envPath);
            Dotenv\Dotenv::createMutable($basePath)->safeLoad();
        }
        $appKey = $readEnv('APP_KEY');
        if ($appKey === '') {
            $appKey = 'base64:'.base64_encode(random_bytes(32));
            $contents = is_file($envPath) ? file_get_contents($envPath) : '';
            if (preg_match('/^APP_KEY=.*$/m', $contents)) {
                $contents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$appKey, $contents, 1);
            } else {
                $contents = rtrim($contents).PHP_EOL.'APP_KEY='.$appKey.PHP_EOL;
            }
            if (file_put_contents($envPath, $contents, LOCK_EX) === false) {
                http_response_code(500);
                exit('Không thể lưu APP_KEY. Hãy cấp quyền ghi cho file .env để bắt đầu cài đặt.');
            }
        }

        foreach (['APP_KEY' => $appKey, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync', 'APP_DEBUG' => 'false'] as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }
}

// A marker alone is not proof that the configured database is still available.
// Recheck legacy installations so a removed database can be set up again.
if (is_file($installedFlag) && ! defined('QLBH_DATABASE_INSTALLED')) {
    $driver = $readEnv('DB_CONNECTION', 'mysql');
    $database = $readEnv('DB_DATABASE', '');
    $host = $readEnv('DB_HOST', '127.0.0.1');
    $port = $readEnv('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
    $username = $readEnv('DB_USERNAME');
    $password = $readEnv('DB_PASSWORD');
    $connectionFailed = false;
    $hasUsers = false;

    try {
        $dsn = match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
            'sqlite' => 'sqlite:'.((preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $database)) ? $database : $basePath.'/'.ltrim($database, '/\\')),
            default => '',
        };
        if ($dsn !== '') {
            $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
            if ($driver === 'mysql') {
                $hasUsers = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            } elseif ($driver === 'pgsql') {
                $hasUsers = (bool) $pdo->query("SELECT to_regclass('public.users')")->fetchColumn();
            } else {
                $hasUsers = (bool) $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
            }
            if ($hasUsers) {
                $hasUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
            }
        }
    } catch (Throwable $exception) {
        $connectionFailed = true;
    }

    define('QLBH_DATABASE_INSTALLED', $hasUsers);
    if ($connectionFailed) {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync', 'APP_DEBUG' => 'false'] as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }
}

require __DIR__.'/index.php';
