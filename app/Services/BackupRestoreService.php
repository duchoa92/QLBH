<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupRestoreService
{
    public function inspect(string $name, ?callable $progress = null): array
    {
        $progress ??= static fn (int $percent, string $message) => null;
        $progress(2, 'Đang mở và kiểm tra tính toàn vẹn tệp ZIP.');
        $zip = $this->open($name);
        try {
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
            $dump = $zip->getFromName('database.sql');
            if (! is_string($dump) || $dump === '') throw new RuntimeException('Bản sao lưu không có database.sql.');
            $driver = DB::connection()->getDriverName();
            if (! in_array($driver, ['mysql', 'sqlite'], true)) throw new RuntimeException('Khôi phục hiện hỗ trợ MySQL/MariaDB và SQLite.');
            if (isset($manifest['database']) && $manifest['database'] !== $driver) throw new RuntimeException('Driver bản sao lưu không khớp cơ sở dữ liệu hiện tại.');

            $tables = [];
            $backupHashes = [];
            foreach ($this->splitStatements($dump) as $statement) {
                if (! preg_match('/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:\x60([^\x60]+)\x60|"([^"]+)"|([\w.-]+))/i', $statement, $create)) continue;
                $tableName = $create[1] ?: ($create[2] ?: $create[3]);
                $tables[$tableName] = ['backup_rows' => 0, 'columns' => $this->parseCreateColumns($statement)];
            }
            foreach (preg_split('/\R/', $dump) as $line) {
                if (! preg_match('/^\s*INSERT\s+INTO\s+(\x60([^\x60]+)\x60|"([^"]+)"|([\w.-]+))\s*\((.*?)\)\s*VALUES\s*\(/i', $line, $m)) continue;
                $table = $m[2] ?: ($m[3] ?: $m[4]);
                $columns = array_map(fn ($value) => trim($value, " ".chr(96)."\"\t"), explode(',', $m[5]));
                $tables[$table] ??= ['backup_rows' => 0, 'columns' => $columns];
                if ($tables[$table]['columns'] !== [] && $tables[$table]['columns'] !== $columns) throw new RuntimeException("Cột dữ liệu trong bảng {$table} không đồng nhất.");
                $tables[$table]['columns'] = $columns;
                $tables[$table]['backup_rows']++;
                $backupHashes[$table] ??= hash_init('sha256');
                hash_update($backupHashes[$table], rtrim($line, "\r\n")."\n");
            }
            $progress(25, 'Đang đối chiếu cấu trúc các bảng.');
            if ($tables === []) throw new RuntimeException('Không tìm thấy dữ liệu bảng trong bản sao lưu.');
            foreach ($tables as $table => $_) $backupHashes[$table] ??= hash_init('sha256');
            $missingCoreTables = array_diff(['users', 'migrations', 'settings'], array_keys($tables));
            if ($missingCoreTables !== []) {
                throw new RuntimeException('Bản sao lưu thiếu bảng hệ thống quan trọng: '.implode(', ', $missingCoreTables).'. Không thể khôi phục an toàn.');
            }

            $currentTables = $driver === 'mysql'
                ? array_map(fn ($row) => array_values((array) $row)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
                : array_column(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
            $summary = ['backup_tables' => count($tables), 'current_tables' => count($currentTables), 'backup_rows' => 0, 'current_rows' => 0, 'new_tables' => 0, 'missing_tables' => 0, 'mismatched_data_tables' => 0, 'schema_compatible' => true];
            $currentColumns = [];
            $primaryColumns = [];
            $primaryTypes = [];
            $tablesWithUniqueKeys = [];
            if ($driver === 'mysql') {
                $columnRows = DB::select('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION');
                foreach ($columnRows as $columnRow) $currentColumns[$columnRow->TABLE_NAME][] = $columnRow->COLUMN_NAME;
                $uniqueRows = DB::select('SELECT DISTINCT TABLE_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0');
                $tablesWithUniqueKeys = array_fill_keys(array_map(fn ($row) => $row->TABLE_NAME, $uniqueRows), true);
                $primaryRows = DB::select("SELECT s.TABLE_NAME, s.COLUMN_NAME, s.SEQ_IN_INDEX, c.DATA_TYPE FROM information_schema.STATISTICS s JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME WHERE s.TABLE_SCHEMA = DATABASE() AND s.INDEX_NAME = 'PRIMARY' ORDER BY s.TABLE_NAME, s.SEQ_IN_INDEX");
                foreach ($primaryRows as $row) {
                    $primaryColumns[$row->TABLE_NAME][] = $row->COLUMN_NAME;
                    $primaryTypes[$row->TABLE_NAME][] = $row->DATA_TYPE;
                }
            } else {
                foreach ($currentTables as $currentTable) {
                    $currentColumns[$currentTable] = DB::getSchemaBuilder()->getColumnListing($currentTable);
                    $quoted = '"'.str_replace('"', '""', $currentTable).'"';
                    foreach (DB::select('PRAGMA table_info('.$quoted.')') as $column) {
                        if ((int) $column->pk > 0) {
                            $primaryColumns[$currentTable][(int) $column->pk - 1] = $column->name;
                            $primaryTypes[$currentTable][(int) $column->pk - 1] = strtolower((string) $column->type);
                        }
                    }
                    foreach (DB::select('PRAGMA index_list('.$quoted.')') as $index) {
                        if ((int) $index->unique === 1) $tablesWithUniqueKeys[$currentTable] = true;
                    }
                    if (isset($primaryColumns[$currentTable])) {
                        ksort($primaryColumns[$currentTable]);
                        ksort($primaryTypes[$currentTable]);
                        $primaryColumns[$currentTable] = array_values($primaryColumns[$currentTable]);
                        $primaryTypes[$currentTable] = array_values($primaryTypes[$currentTable]);
                        $tablesWithUniqueKeys[$currentTable] = true;
                    }
                }
            }
            $q = $driver === 'mysql' ? chr(96) : '"';
            $countQueries = array_map(function ($currentTable) use ($q) {
                $quoted = $q.str_replace($q, $q.$q, $currentTable).$q;
                return 'SELECT '.DB::connection()->getPdo()->quote($currentTable).' AS table_name, COUNT(*) AS aggregate FROM '.$quoted;
            }, $currentTables);
            $currentCounts = [];
            foreach (DB::select(implode(' UNION ALL ', $countQueries)) as $countRow) $currentCounts[$countRow->table_name] = (int) $countRow->aggregate;
            $tableIndex = 0;
            foreach ($tables as $table => &$info) {
                $summary['backup_rows'] += $info['backup_rows'];
                $info['exists'] = in_array($table, $currentTables, true);
                $info['current_rows'] = $currentCounts[$table] ?? 0;
                $info['columns_missing_from_current'] = array_values(array_diff($info['columns'], $currentColumns[$table] ?? []));
                $info['columns_missing_from_backup'] = array_values(array_diff($currentColumns[$table] ?? [], $info['columns']));
                $hasUniqueKey = $info['exists'] && ($info['backup_rows'] === 0 || isset($tablesWithUniqueKeys[$table]));
                $info['schema_compatible'] = $info['exists']
                    && ($currentColumns[$table] ?? []) === $info['columns']
                    && $hasUniqueKey;
                $info['primary_key_conflicts'] = null;
                if ($info['exists'] && count($primaryColumns[$table] ?? []) === 1
                    && in_array($primaryColumns[$table][0], $info['columns'], true)
                    && preg_match('/^(?:tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric)$/i', $primaryTypes[$table][0] ?? '')) {
                    $info['primary_key_conflicts'] = 0;
                }
                $backupChecksum = hash_final($backupHashes[$table]);
                $currentHash = hash_init('sha256');
                if ($info['exists'] && $info['schema_compatible']) {
                    $q = $driver === 'mysql' ? chr(96) : '"';
                    $quotedTableSql = $q.str_replace($q, $q.$q, $table).$q;
                    $quotedColumns = implode(', ', array_map(fn ($column) => $q.str_replace($q, $q.$q, $column).$q, $info['columns']));
                    $processedRows = 0;
                    $totalTableRows = max(1, $info['current_rows']);
                    foreach (DB::table($table)->orderBy($info['columns'][0])->cursor() as $row) {
                        $values = [];
                        foreach ((array) $row as $value) $values[] = $value === null ? 'NULL' : DB::connection()->getPdo()->quote((string) $value);
                        hash_update($currentHash, 'INSERT INTO '.$quotedTableSql.' ('.$quotedColumns.') VALUES ('.implode(', ', $values).");\n");
                        $processedRows++;
                        if ($processedRows % 100 === 0) {
                            $fraction = ($tableIndex + min(1, $processedRows / $totalTableRows)) / max(1, count($tables));
                            $progress(25 + (int) floor($fraction * 60), 'Đang đối chiếu bảng '.$table.' ('.number_format($processedRows).' / '.number_format($info['current_rows']).' dòng).');
                        }
                    }
                }
                $info['data_matches'] = $info['exists']
                    && $info['backup_rows'] === $info['current_rows']
                    && hash_equals($backupChecksum, hash_final($currentHash));
                if (! $info['data_matches']) $summary['mismatched_data_tables']++;
                $summary['current_rows'] += $info['current_rows'];
                if (! $info['exists']) $summary['new_tables']++;
                if (! $info['schema_compatible']) $summary['schema_compatible'] = false;
                $tableIndex++;
                $progress(25 + (int) floor($tableIndex / max(1, count($tables)) * 60), 'Đang so sánh dữ liệu bảng '.$table.' ('.$tableIndex.' / '.count($tables).').');
            }
            unset($info);
            $pendingPrimaryKeys = [];
            $checkedPrimaryKeyRows = 0;
            foreach (preg_split('/\R/', $dump) as $line) {
                if (! preg_match('/^\s*INSERT\s+INTO\s+(?:\x60([^\x60]+)\x60|"([^"]+)"|([\w.-]+))\s*\((.*?)\)\s*VALUES\s*\((.*)\)\s*;\s*$/i', $line, $insert)) continue;
                $table = $insert[1] ?: ($insert[2] ?: $insert[3]);
                $primary = $primaryColumns[$table] ?? [];
                if (count($primary) !== 1 || ! isset($tables[$table]) || ! $tables[$table]['exists']) continue;
                $columns = array_map(fn ($value) => trim($value, " ".chr(96)."\"\t"), explode(',', $insert[4]));
                $primaryIndex = array_search($primary[0], $columns, true);
                if ($primaryIndex === false) continue;
                $values = $this->splitSqlValues($insert[5]);
                $rawKey = $values[$primaryIndex] ?? '';
                if (! preg_match("/^(?:'(-?\\d+)'|(-?\\d+))$/", $rawKey, $keyMatch)) continue;
                $pendingPrimaryKeys[$table][] = $keyMatch[1] !== '' ? $keyMatch[1] : $keyMatch[2];
                $checkedPrimaryKeyRows++;
                if (count($pendingPrimaryKeys[$table]) >= 500) {
                    $tables[$table]['primary_key_conflicts'] += $this->countPrimaryKeyConflicts($table, $primary[0], $pendingPrimaryKeys[$table]);
                    $pendingPrimaryKeys[$table] = [];
                    if ($checkedPrimaryKeyRows % 500 === 0) {
                        $percent = 85 + min(9, (int) floor($checkedPrimaryKeyRows / max(1, $summary['backup_rows']) * 9));
                        $progress($percent, 'Đang kiểm tra dòng trùng khóa chính ('.number_format($checkedPrimaryKeyRows).' / '.number_format($summary['backup_rows']).').');
                    }
                }
            }
            foreach ($pendingPrimaryKeys as $table => $keys) {
                if ($keys !== []) $tables[$table]['primary_key_conflicts'] += $this->countPrimaryKeyConflicts($table, $primaryColumns[$table][0], $keys);
            }
            $hasUnknownPrimaryKeyConflicts = collect($tables)->contains(fn ($table) => $table['backup_rows'] > 0 && $table['primary_key_conflicts'] === null);
            $summary['primary_key_conflicts'] = $hasUnknownPrimaryKeyConflicts
                ? null
                : array_sum(array_map(fn ($table) => (int) ($table['primary_key_conflicts'] ?? 0), $tables));
            $summary['current_rows'] = array_sum($currentCounts);
            $summary['missing_tables'] = count(array_diff($currentTables, array_keys($tables)));
            $summary['row_count_difference'] = $summary['backup_rows'] - $summary['current_rows'];
            $summary['tables_missing_from_backup'] = array_values(array_diff($currentTables, array_keys($tables)));
            $summary['tables_missing_from_current'] = array_values(array_diff(array_keys($tables), $currentTables));
            $fileDetails = [];
            $fileCount = 0;
            $fileBytes = 0;
            for ($entryIndex = 0; $entryIndex < $zip->numFiles; $entryIndex++) {
                $entry = $zip->getNameIndex($entryIndex);
                if (! is_string($entry) || str_ends_with($entry, '/')) continue;
                $stat = $zip->statIndex($entryIndex);
                $stream = $zip->getStream($entry);
                if ($stream === false) throw new RuntimeException('Không thể đọc mục '.$entry.' trong bản sao lưu.');
                $checksum = hash_init('crc32b');
                $bytes = 0;
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false) { fclose($stream); throw new RuntimeException('Không thể đọc hết mục '.$entry.' trong bản sao lưu.'); }
                    $bytes += strlen($chunk);
                    hash_update($checksum, $chunk);
                }
                fclose($stream);
                if ($bytes !== (int) ($stat['size'] ?? -1) || strtolower(hash_final($checksum)) !== sprintf('%08x', $stat['crc'] ?? 0)) {
                    throw new RuntimeException('Dữ liệu hoặc checksum không khớp tại '.$entry.'.');
                }
                if (str_starts_with($entry, 'files/public/') || str_starts_with($entry, 'files/private/')) {
                    $fileCount++;
                    $fileBytes += $bytes;
                    if (count($fileDetails) < 200) $fileDetails[] = ['path' => $entry, 'size' => $bytes];
                }
                $progress(95 + (int) floor(($entryIndex + 1) / max(1, $zip->numFiles) * 4), 'Đang xác minh tệp và checksum ('.($entryIndex + 1).' / '.$zip->numFiles.' mục).');
            }
            $manifestContents = $manifest['contents'] ?? [];
            if (isset($manifestContents['table_count']) && (int) $manifestContents['table_count'] !== count($tables)) {
                throw new RuntimeException('Số bảng đọc được không khớp manifest của bản sao lưu.');
            }
            if (isset($manifestContents['row_count']) && (int) $manifestContents['row_count'] !== $summary['backup_rows']) {
                throw new RuntimeException('Số dòng đọc được không khớp manifest của bản sao lưu.');
            }
            if (isset($manifestContents['uploaded_files']) && (int) $manifestContents['uploaded_files'] !== $fileCount) {
                throw new RuntimeException('Số tệp đọc được không khớp manifest của bản sao lưu.');
            }
            if (isset($manifestContents['uploaded_bytes']) && (int) $manifestContents['uploaded_bytes'] !== $fileBytes) {
                throw new RuntimeException('Dung lượng tệp đọc được không khớp manifest của bản sao lưu.');
            }
            $progress(100, 'Đã kiểm tra xong cấu trúc, dữ liệu và danh sách tệp.');
            return [
                'valid' => true, 'backup' => $name, 'created_at' => $manifest['created_at'] ?? null,
                'summary' => $summary, 'tables' => $tables,
                'has_files' => $fileCount > 0, 'files_count' => $fileCount, 'files_total_bytes' => $fileBytes,
                'files_omitted' => max(0, $fileCount - count($fileDetails)), 'files' => $fileDetails,
            ];
        } finally {
            $zip->close();
        }
    }

    public function restore(string $name, string $mode, string $conflictPolicy, BackupService $backupService, ?callable $progress = null): array
    {
        $progress ??= static fn (int $percent, string $message) => null;
        $inspection = $this->inspect($name, fn ($percent, $message) => $progress((int) ($percent * 0.2), $message));
        $zip = $this->open($name);
        $dump = $zip->getFromName('database.sql');
        $zip->close();
        if (! is_string($dump)) throw new RuntimeException('Không đọc được dữ liệu sao lưu.');

        if ($mode === 'merge') {
            if (! $inspection['summary']['schema_compatible'] || $inspection['summary']['new_tables'] > 0) {
                throw new RuntimeException('Cấu trúc bảng đã lệch. Chọn thay toàn bộ dữ liệu hoặc hủy để tránh gộp sai cột.');
            }
            $progress(22, 'Đang tạo bản cứu hộ trước khi gộp dữ liệu.');
            $recovery = $backupService->create(fn ($percent, $message) => $progress(22 + (int) ($percent * 0.18), 'Bản cứu hộ: '.$message));
            try {
                $this->setForeignKeyChecks(false);
                $mergeReport = [
                    'tables' => array_fill_keys(array_keys($inspection['tables']), ['added' => 0, 'overwritten' => 0, 'kept_or_unchanged' => 0]),
                    'files' => ['added' => 0, 'overwritten' => 0, 'skipped' => 0, 'deleted' => 0],
                ];
                DB::transaction(function () use ($dump, $conflictPolicy, $name, $progress, &$mergeReport) {
                    $orphanCountsBefore = $this->foreignKeyOrphanCounts();
                    $lines = preg_split('/\R/', $dump);
                    $totalLines = max(1, count($lines));
                    foreach ($lines as $lineIndex => $line) {
                        if (! preg_match('/^\s*INSERT\s+INTO\s+(\x60([^\x60]+)\x60|"([^"]+)"|([\w.-]+))\s*\((.*?)\)\s*(VALUES\s*\(.*\))\s*;\s*$/i', $line, $m)) continue;
                        $columns = array_map(fn ($value) => trim($value, " ".chr(96)."\"\t"), explode(',', $m[5]));
                        $sql = 'INSERT INTO '.$m[1].' ('.$m[5].') '.$m[6];
                        $driver = DB::connection()->getDriverName();
                        if ($conflictPolicy === 'keep_current') {
                            $sql = preg_replace('/^\s*INSERT\s+INTO/i', $driver === 'sqlite' ? 'INSERT OR IGNORE INTO' : 'INSERT IGNORE INTO', $sql, 1);
                        } elseif ($driver === 'sqlite') {
                            $sql = preg_replace('/^\s*INSERT\s+INTO/i', 'INSERT OR REPLACE INTO', $sql, 1);
                        } else {
                            $q = chr(96);
                            $updates = array_map(fn ($column) => $q.$column.$q.'=VALUES('.$q.$column.$q.')', $columns);
                            $sql = rtrim($sql, ';').' ON DUPLICATE KEY UPDATE '.implode(', ', $updates);
                        }
                        $affected = DB::connection()->getPdo()->exec($sql);
                        $table = $m[2] ?: ($m[3] ?: $m[4]);
                        $mergeReport['tables'][$table] ??= ['added' => 0, 'overwritten' => 0, 'kept_or_unchanged' => 0];
                        if ($conflictPolicy === 'keep_current') {
                            if ($affected > 0) $mergeReport['tables'][$table]['added']++;
                            else $mergeReport['tables'][$table]['kept_or_unchanged']++;
                        } elseif ($affected === 1) {
                            $mergeReport['tables'][$table]['added']++;
                        } elseif ($affected === 2) {
                            $mergeReport['tables'][$table]['overwritten']++;
                        } else {
                            $mergeReport['tables'][$table]['kept_or_unchanged']++;
                        }
                        if ($lineIndex % 100 === 0) $progress(40 + (int) floor($lineIndex / $totalLines * 50), 'Đang gộp dữ liệu sao lưu.');
                    }
                    foreach ($this->foreignKeyOrphanCounts() as $constraint => $count) {
                        if ($count > ($orphanCountsBefore[$constraint] ?? 0)) {
                            throw new RuntimeException('Gộp sẽ tạo dữ liệu tham chiếu không hợp lệ tại '.$constraint.'. Đã hủy thao tác; hãy chọn giữ dữ liệu hiện tại hoặc thay toàn bộ.');
                        }
                    }
                    $mergeReport['files'] = $this->restoreFiles($name, 'merge', $conflictPolicy, fn ($percent, $message) => $progress(90 + (int) ($percent * 0.09), $message));
                });
                $this->setForeignKeyChecks(true);
                $progress(100, 'Đã gộp dữ liệu thành công.');
            } catch (\Throwable $exception) {
                $this->setForeignKeyChecks(true);
                try { $this->restoreFiles(basename($recovery), 'replace', 'use_backup'); }
                catch (\Throwable $rollbackError) { report($rollbackError); }
                throw new RuntimeException('Không thể gộp dữ liệu; thao tác đã được hoàn tác. Điểm phục hồi: '.basename($recovery).'. '.$exception->getMessage(), previous: $exception);
                }
            return ['mode' => 'merge', 'conflict_policy' => $conflictPolicy, 'summary' => $inspection['summary'], 'has_files' => $inspection['has_files'], 'files_count' => $inspection['files_count'], 'files_total_bytes' => $inspection['files_total_bytes'], 'files_omitted' => $inspection['files_omitted'], 'backup_file_details' => $inspection['files'], 'merge_report' => $mergeReport];
        }

        $progress(22, 'Đang tạo bản cứu hộ trước khi thay dữ liệu.');
        $recovery = $backupService->create(fn ($percent, $message) => $progress(22 + (int) ($percent * 0.18), 'Bản cứu hộ: '.$message));
        try {
            $this->replace($dump, fn ($percent, $message) => $progress(42 + (int) ($percent * 0.38), $message));
            foreach ($this->foreignKeyOrphanCounts() as $constraint => $count) {
                if ($count > 0) throw new RuntimeException('Bản sao lưu tạo tham chiếu dữ liệu không hợp lệ tại '.$constraint.'. Đã phục hồi điểm an toàn.');
            }
            $fileReport = $this->restoreFiles($name, 'replace', $conflictPolicy, fn ($percent, $message) => $progress(80 + (int) ($percent * 0.19), $message));
            $progress(100, 'Khôi phục hoàn tất.');
        } catch (\Throwable $exception) {
            try {
                $recoveryZip = new ZipArchive();
                if ($recoveryZip->open($recovery) === true) {
                    $recoveryDump = $recoveryZip->getFromName('database.sql');
                    $recoveryZip->close();
                    if (is_string($recoveryDump)) $this->replace($recoveryDump);
                }
                $this->restoreFiles(basename($recovery), 'replace', 'use_backup');
            } catch (\Throwable $rollbackError) { report($rollbackError); }
            throw new RuntimeException('Khôi phục bị lỗi; hệ thống đã thử phục hồi dữ liệu trước thao tác. Điểm phục hồi: '.basename($recovery).'. '.$exception->getMessage(), previous: $exception);
        }
        $restoredRows = 0;
        $restoredTables = [];
        foreach (array_keys($inspection['tables']) as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $rowCount = (int) DB::table($table)->count();
                $restoredRows += $rowCount;
                $restoredTables[] = ['table' => $table, 'rows_restored' => $rowCount];
            }
        }
        return ['mode' => 'replace', 'recovery_backup' => basename($recovery), 'summary' => $inspection['summary'], 'has_files' => $inspection['has_files'], 'files_count' => $inspection['files_count'], 'files_total_bytes' => $inspection['files_total_bytes'], 'files_omitted' => $inspection['files_omitted'], 'backup_file_details' => $inspection['files'], 'restored_rows' => $restoredRows, 'table_report' => $restoredTables, 'files' => $fileReport];
    }

    private function open(string $name): ZipArchive
    {
        if (! preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-\d{6})?\.zip$/', $name)) throw new RuntimeException('Tên bản sao lưu không hợp lệ.');
        $path = storage_path('app/backups/'.$name);
        if (! is_file($path)) throw new RuntimeException('Không tìm thấy bản sao lưu.');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Tệp ZIP bị hỏng hoặc không đọc được.');
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (! is_string($entry) || str_contains($entry, '..') || str_starts_with($entry, '/') || str_contains($entry, chr(92))) {
                $zip->close();
                throw new RuntimeException('Tệp sao lưu chứa đường dẫn không hợp lệ.');
            }
        }
        return $zip;
    }

    private function replace(string $dump, ?callable $progress = null): void
    {
        $driver = DB::connection()->getDriverName();
        $this->setForeignKeyChecks(false);
        $q = $driver === 'mysql' ? chr(96) : '"';
        $currentTables = $driver === 'mysql'
            ? array_map(fn ($row) => array_values((array) $row)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            : array_column(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
        foreach ($currentTables as $index => $table) {
            DB::statement('DROP TABLE IF EXISTS '.$q.str_replace($q, $q.$q, $table).$q);
            if ($index % 10 === 0 && $progress) $progress(10 + (int) floor(($index + 1) / max(1, count($currentTables)) * 15), 'Đang chuẩn bị các bảng hiện tại.');
        }
        $statements = iterator_to_array($this->splitStatements($dump), false);
        foreach ($statements as $index => $statement) {
            if (preg_match('/^\s*(?:--.*\R)?\s*(?:SET\s+FOREIGN_KEY_CHECKS|PRAGMA\s+foreign_keys)/i', $statement)) continue;
            DB::unprepared($statement);
            if ($index % 100 === 0 && $progress) $progress(25 + (int) floor(($index + 1) / max(1, count($statements)) * 70), 'Đang khôi phục cấu trúc và dữ liệu.');
        }
        $this->setForeignKeyChecks(true);
        if ($progress) $progress(100, 'Đã khôi phục xong cơ sở dữ liệu.');
    }

    private function restoreFiles(string $name, string $mode, string $conflictPolicy, ?callable $progress = null): array
    {
        $zip = $this->open($name);
        $staging = storage_path('app/backups/.restore-'.uniqid('', true));
        try {
            $entryCount = max(1, $zip->numFiles);
            for ($entryIndex = 0; $entryIndex < $zip->numFiles; $entryIndex++) {
                $entry = $zip->getNameIndex($entryIndex);
                if (! is_string($entry) || ! $zip->extractTo($staging, $entry)) throw new RuntimeException('Không thể giải nén các tệp trong bản sao lưu.');
                if ($progress) $progress((int) floor(($entryIndex + 1) / $entryCount * 45), 'Đang giải nén dữ liệu sao lưu.');
            }
        } finally {
            $zip->close();
        }

        try {
            $files = [];
            foreach (['public', 'private'] as $disk) {
                $source = $staging.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$disk;
                if (is_dir($source)) foreach (File::allFiles($source) as $file) $files[] = [$disk, $source, $file];
            }
            $totalFiles = max(1, count($files));
            $fileReport = ['added' => 0, 'overwritten' => 0, 'skipped' => 0, 'deleted' => 0];
            foreach ($files as $index => [$disk, $source, $file]) {
                $target = storage_path('app/'.$disk);
                $relative = substr($file->getPathname(), strlen($source) + 1);
                $destination = $target.DIRECTORY_SEPARATOR.$relative;
                $alreadyExists = is_file($destination);
                if ($mode === 'merge' && $conflictPolicy === 'keep_current' && $alreadyExists) {
                    $fileReport['skipped']++;
                    continue;
                }
                File::ensureDirectoryExists(dirname($destination));
                if (! File::copy($file->getPathname(), $destination)) throw new RuntimeException('Không chép được tệp '.$relative.' ra vị trí khôi phục.');
                $fileReport[$alreadyExists ? 'overwritten' : 'added']++;
                if ($progress) $progress(45 + (int) floor(($index + 1) / $totalFiles * 55), 'Đang khôi phục tệp tải lên.');
            }
            if ($mode === 'replace') {
                foreach (['public', 'private'] as $disk) {
                    $target = storage_path('app/'.$disk);
                    File::ensureDirectoryExists($target);
                    $restored = $staging.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$disk;
                    $existingFiles = File::allFiles($target);
                    foreach ($existingFiles as $existing) {
                        $relative = substr($existing->getPathname(), strlen($target) + 1);
                        if (! is_file($restored.DIRECTORY_SEPARATOR.$relative)) {
                            File::delete($existing->getPathname());
                            $fileReport['deleted']++;
                        }
                    }
                }
            }
        } finally {
            File::deleteDirectory($staging);
        }
        return $fileReport;
    }

    private function foreignKeyOrphanCounts(): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $counts = [];
            foreach (DB::select('PRAGMA foreign_key_check') as $violation) {
                $key = $violation->table.'.'.($violation->fkid ?? 'foreign_key');
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
            return $counts;
        }
        $constraints = DB::select(
            'SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION'
        );
        $groups = [];
        foreach ($constraints as $constraint) {
            $key = $constraint->TABLE_NAME.'.'.$constraint->CONSTRAINT_NAME;
            $groups[$key]['table'] = $constraint->TABLE_NAME;
            $groups[$key]['parent'] = $constraint->REFERENCED_TABLE_NAME;
            $groups[$key]['columns'][] = [$constraint->COLUMN_NAME, $constraint->REFERENCED_COLUMN_NAME];
        }

        $counts = [];
        foreach ($groups as $key => $group) {
            $q = fn ($identifier) => chr(96).str_replace(chr(96), chr(96).chr(96), $identifier).chr(96);
            $joins = [];
            $nonnull = [];
            foreach ($group['columns'] as [$column, $referenced]) {
                $joins[] = 'c.'.$q($column).' = p.'.$q($referenced);
                $nonnull[] = 'c.'.$q($column).' IS NOT NULL';
            }
            $parentKey = $group['columns'][0][1];
            $sql = 'SELECT COUNT(*) AS aggregate FROM '.$q($group['table']).' AS c LEFT JOIN '.$q($group['parent']).' AS p ON '.implode(' AND ', $joins)
                .' WHERE '.implode(' AND ', $nonnull).' AND p.'.$q($parentKey).' IS NULL';
            $counts[$key] = (int) DB::selectOne($sql)->aggregate;
        }
        return $counts;
    }

    private function setForeignKeyChecks(bool $enabled): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys='.($enabled ? 'ON' : 'OFF'));
            return;
        }
        DB::statement('SET FOREIGN_KEY_CHECKS='.($enabled ? '1' : '0'));
    }

    private function parseCreateColumns(string $statement): array
    {
        $open = strpos($statement, '(');
        if ($open === false) return [];
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $escaped = false;
        for ($i = $open + 1, $length = strlen($statement); $i < $length; $i++) {
            $char = $statement[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) { $escaped = false; continue; }
                if ($char === chr(92) && $quote === "'") { $escaped = true; continue; }
                if ($char === $quote) {
                    if (($statement[$i + 1] ?? null) === $quote) { $buffer .= $statement[++$i]; continue; }
                    $quote = null;
                }
                continue;
            }
            if (in_array($char, ["'", '"', chr(96)], true)) { $quote = $char; $buffer .= $char; continue; }
            if ($char === '(') { $depth++; $buffer .= $char; continue; }
            if ($char === ')') {
                if ($depth === 0) { if (trim($buffer) !== '') $parts[] = trim($buffer); break; }
                $depth--; $buffer .= $char; continue;
            }
            if ($char === ',' && $depth === 0) { $parts[] = trim($buffer); $buffer = ''; continue; }
            $buffer .= $char;
        }
        $columns = [];
        foreach ($parts as $part) {
            // MySQL SHOW CREATE TABLE emits KEY/INDEX clauses alongside columns.
            // They are table constraints and must not be mistaken for data columns.
            if (preg_match('/^(?:CONSTRAINT|PRIMARY\s+KEY|UNIQUE|FOREIGN\s+KEY|KEY|INDEX|FULLTEXT|SPATIAL|CHECK)\b/i', $part)) continue;
            if (preg_match('/^(?:\x60([^\x60]+)\x60|"([^"]+)"|\[([^\]]+)\]|([\w.-]+))\s+/i', $part, $m)) {
                $columns[] = $m[1] ?: ($m[2] ?: ($m[3] ?: $m[4]));
            }
        }
        return $columns;
    }

    private function splitStatements(string $sql): \Generator
    {
        $buffer = '';
        $quote = null;
        $escaped = false;
        for ($i = 0, $length = strlen($sql); $i < $length; $i++) {
            $char = $sql[$i];
            $buffer .= $char;
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === chr(92) && $quote !== chr(96)) { $escaped = true; continue; }
                if ($char === $quote) {
                    if (($sql[$i + 1] ?? null) === $quote) { $buffer .= $sql[++$i]; continue; }
                    $quote = null;
                }
            } elseif (in_array($char, ["'", '"', chr(96)], true)) {
                $quote = $char;
            } elseif ($char === ';') {
                $statement = trim(substr($buffer, 0, -1));
                if ($statement !== '') yield $statement;
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') yield trim($buffer);
    }

    private function splitSqlValues(string $values): array
    {
        $parts = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        for ($index = 0, $length = strlen($values); $index < $length; $index++) {
            $char = $values[$index];
            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) { $escaped = false; continue; }
                if ($char === chr(92) && $quote !== chr(96)) { $escaped = true; continue; }
                if ($char === $quote) {
                    if (($values[$index + 1] ?? null) === $quote) { $buffer .= $values[++$index]; continue; }
                    $quote = null;
                }
                continue;
            }
            if (in_array($char, ["'", '"', chr(96)], true)) { $quote = $char; $buffer .= $char; continue; }
            if ($char === ',') { $parts[] = trim($buffer); $buffer = ''; continue; }
            $buffer .= $char;
        }
        $parts[] = trim($buffer);
        return $parts;
    }

    private function countPrimaryKeyConflicts(string $table, string $column, array $keys): int
    {
        $quote = fn ($identifier) => chr(96).str_replace(chr(96), chr(96).chr(96), $identifier).chr(96);
        $keys = array_values(array_unique(array_filter($keys, fn ($key) => preg_match('/^-?\d+$/', $key))));
        if ($keys === []) return 0;
        $identifierQuote = DB::connection()->getDriverName() === 'mysql' ? chr(96) : '"';
        $quote = fn ($identifier) => $identifierQuote.str_replace($identifierQuote, $identifierQuote.$identifierQuote, $identifier).$identifierQuote;
        return (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM '.$quote($table).' WHERE '.$quote($column).' IN ('.implode(',', $keys).')')->aggregate;
    }
}
