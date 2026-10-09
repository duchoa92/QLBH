<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupService
{
    public function create(?callable $progress = null): string
    {
        $progress ??= static fn (int $percent, string $message) => null;
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $stamp = now()->format('Y-m-d_H-i-s-u');
        $working = $directory.DIRECTORY_SEPARATOR.'.backup-'.$stamp.'-'.uniqid();
        File::ensureDirectoryExists($working);

        try {
            $databaseFile = $working.DIRECTORY_SEPARATOR.'database.sql';
            $progress(3, 'Đang sao lưu cấu trúc và dữ liệu cơ sở dữ liệu.');
            $this->dumpDatabase($databaseFile, fn ($percent, $message) => $progress(3 + (int) ($percent * 0.42), $message));

            $filesDirectory = $working.DIRECTORY_SEPARATOR.'files';
            File::ensureDirectoryExists($filesDirectory);
            $progress(47, 'Đang sao lưu tệp đã tải lên.');
            $sources = array_values(array_filter([storage_path('app/public'), storage_path('app/private')], 'is_dir'));
            $copyList = [];
            foreach ($sources as $source) foreach (File::allFiles($source) as $file) $copyList[] = [$source, $file];
            $copyTotal = max(1, count($copyList));
            foreach ($copyList as $index => [$source, $file]) {
                $relative = substr($file->getPathname(), strlen($source) + 1);
                $destination = $filesDirectory.DIRECTORY_SEPARATOR.basename($source).DIRECTORY_SEPARATOR.$relative;
                File::ensureDirectoryExists(dirname($destination));
                File::copy($file->getPathname(), $destination);
                $progress(47 + (int) floor(($index + 1) / $copyTotal * 25), 'Đang sao lưu tệp tải lên ('.($index + 1).' / '.count($copyList).').');
            }
            $progress(72, 'Đã sao lưu các tệp; đang đóng gói bản sao lưu.');

            File::put($working.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
                'created_at' => now()->toIso8601String(),
                'database' => config('database.default'),
                'includes' => ['database', 'storage/app/public', 'storage/app/private'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            $archivePath = $directory.DIRECTORY_SEPARATOR.'backup_'.$stamp.'.zip';
            $zip = new ZipArchive();
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Không thể tạo tệp ZIP sao lưu.');
            }
            $zip->addEmptyDir('files/public');
            $zip->addEmptyDir('files/private');
            $this->addDirectoryToZip($zip, $working, '', fn ($percent) => $progress(72 + (int) ($percent * 0.27), 'Đang đóng gói bản sao lưu.'));
            $zip->close();
            $progress(100, 'Bản sao lưu đã được tạo.');

            return $archivePath;
        } finally {
            File::deleteDirectory($working);
        }
    }

    private function dumpDatabase(string $path, ?callable $progress = null): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName();
        $tables = match ($driver) {
            'mysql' => array_map(fn ($row) => array_values((array) $row)[0], $connection->select('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')),
            'sqlite' => array_column($connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name'),
            default => throw new RuntimeException("Driver cơ sở dữ liệu '{$driver}' chưa được hỗ trợ sao lưu."),
        };
        $rowCounts = [];
        $totalRows = 0;
        foreach ($tables as $table) {
            $rowCounts[$table] = (int) $connection->table($table)->count();
            $totalRows += $rowCounts[$table];
        }

        $stream = fopen($path, 'wb');
        if ($stream === false) throw new RuntimeException('Không thể tạo tệp dữ liệu sao lưu.');
        fwrite($stream, "-- QLBH database backup: ".now()->toIso8601String()."\n");
        fwrite($stream, $driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS=0;\n" : "PRAGMA foreign_keys=OFF;\n");
        $processedRows = 0;
        foreach ($tables as $tableIndex => $table) {
            $quotedTable = $driver === 'mysql' ? '`'.str_replace('`', '``', $table).'`' : '"'.str_replace('"', '""', $table).'"';
            if ($driver === 'mysql') {
                $definition = $connection->selectOne('SHOW CREATE TABLE '.$quotedTable);
                $createSql = array_values((array) $definition)[1] ?? null;
            } else {
                $definition = $connection->selectOne('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?', ['table', $table]);
                $createSql = $definition->sql ?? null;
            }
            if ($createSql) {
                fwrite($stream, "\nDROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n");
            }

            $columns = $connection->getSchemaBuilder()->getColumnListing($table);
            if ($columns === []) continue;
            $quotedColumns = implode(', ', array_map(fn ($column) => $driver === 'mysql'
                ? '`'.str_replace('`', '``', $column).'`'
                : '"'.str_replace('"', '""', $column).'"', $columns));
            foreach ($connection->table($table)->orderBy($columns[0])->cursor() as $row) {
                $values = [];
                foreach ((array) $row as $value) {
                    $values[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
                }
                fwrite($stream, 'INSERT INTO '.$quotedTable.' ('.$quotedColumns.') VALUES ('.implode(', ', $values).");\n");
                $processedRows++;
                if ($totalRows > 0 && $processedRows % 100 === 0) {
                    if ($progress) $progress((int) floor($processedRows / $totalRows * 100), 'Đang sao lưu dữ liệu: '.number_format($processedRows).' / '.number_format($totalRows).' dòng.');
                }
            }
            if ($totalRows === 0 && $progress) $progress((int) floor(($tableIndex + 1) / max(1, count($tables)) * 100), 'Đang sao lưu cấu trúc bảng.');
        }
        if ($progress) $progress(100, 'Đã đọc xong cơ sở dữ liệu.');
        if ($driver === 'sqlite') {
            $sqliteObjects = $connection->select("SELECT sql FROM sqlite_master WHERE type IN ('index', 'trigger') AND sql IS NOT NULL ORDER BY type, name");
            foreach ($sqliteObjects as $object) fwrite($stream, "\n{$object->sql};\n");
            fwrite($stream, "\nPRAGMA foreign_keys=ON;\n");
        } else {
            fwrite($stream, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        }
        fclose($stream);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $prefix, ?callable $progress = null): void
    {
        $files = File::allFiles($directory);
        $total = count($files);
        foreach ($files as $index => $file) {
            $relative = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($directory)), DIRECTORY_SEPARATOR));
            $zip->addFile($file->getPathname(), ltrim($prefix.'/'.$relative, '/'));
            if ($progress) $progress($total === 0 ? 100 : (int) floor(($index + 1) / $total * 100));
        }
    }
}
