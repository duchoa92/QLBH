<?php

namespace App\Services;

use App\Models\BackupCloudConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudBackupStorage
{
    public function test(BackupCloudConnection $connection): void
    {
        $this->assertSupportedProvider($connection);
        if ($connection->provider === 'google_drive') {
            $folderId = $this->googleFolderId($connection);
            $this->googleApi($connection)->get('https://www.googleapis.com/drive/v3/files', ['q' => "'{$folderId}' in parents and trashed = false", 'pageSize' => 1, 'fields' => 'files(id)'])->throw();
            return;
        }
        if ($connection->provider === 'onedrive') {
            $this->oneDriveFolderId($connection);
            return;
        }
        throw new RuntimeException('Dịch vụ lưu trữ đám mây không được hỗ trợ.');
    }

    public function upload(BackupCloudConnection $connection, string $localPath, string $filename, ?callable $progress = null): void
    {
        $this->assertSupportedProvider($connection);
        $this->assertBackupName($filename);
        $progress?->__invoke(5, 'Đang kết nối kho đám mây '.$connection->name.'.');
        if ($connection->provider === 'google_drive') {
            $this->uploadGoogleDrive($connection, $localPath, $filename, $progress);
        } else {
            $this->uploadOneDrive($connection, $localPath, $filename, $progress);
        }
        $progress?->__invoke(100, 'Đã tải bản sao lưu lên '.$connection->name.'.');
    }

    public function backups(BackupCloudConnection $connection): array
    {
        $this->assertSupportedProvider($connection);
        if ($connection->provider === 'google_drive') {
            $folderId = $this->googleFolderId($connection);
            $files = [];
            $pageToken = null;
            do {
                $response = $this->googleApi($connection)->get('https://www.googleapis.com/drive/v3/files', array_filter([
                    'q' => "'{$folderId}' in parents and trashed = false", 'pageSize' => 1000,
                    'fields' => 'nextPageToken,files(id,name,size,modifiedTime)', 'pageToken' => $pageToken,
                    'supportsAllDrives' => 'true',
                ], fn ($value) => $value !== null))->throw()->json();
                foreach ($response['files'] ?? [] as $file) {
                    if ($this->isBackupName($file['name'] ?? '')) $files[] = ['name' => $file['name'], 'size' => (int) ($file['size'] ?? 0), 'created_at' => isset($file['modifiedTime']) ? date('Y-m-d H:i:s', strtotime($file['modifiedTime'])) : null];
                }
                $pageToken = $response['nextPageToken'] ?? null;
            } while ($pageToken);
            return $files;
        }

        if ($connection->provider === 'onedrive') {
            $files = [];
            $url = 'https://graph.microsoft.com/v1.0/me/drive/items/'.rawurlencode($this->oneDriveFolderId($connection)).'/children?'.http_build_query(['$select' => 'id,name,size,lastModifiedDateTime', '$top' => 200]);
            while ($url) {
                $response = $this->graphApi($connection)->get($url)->throw()->json();
                foreach ($response['value'] ?? [] as $file) {
                    if ($this->isBackupName($file['name'] ?? '')) $files[] = ['name' => $file['name'], 'size' => (int) ($file['size'] ?? 0), 'created_at' => isset($file['lastModifiedDateTime']) ? date('Y-m-d H:i:s', strtotime($file['lastModifiedDateTime'])) : null];
                }
                $url = $response['@odata.nextLink'] ?? null;
            }
            return $files;
        }

        throw new RuntimeException('Dịch vụ lưu trữ đám mây không được hỗ trợ.');
    }

    public function download(BackupCloudConnection $connection, string $filename, string $destination, ?callable $progress = null): void
    {
        $this->assertSupportedProvider($connection);
        $this->assertBackupName($filename);
        if ($connection->provider === 'google_drive') {
            $file = $this->googleFileByName($connection, $filename);
            $response = $this->googleApi($connection)->withOptions(['progress' => $this->transferProgress($progress, 'Đang tải bản sao lưu từ Google Drive.')])->sink($destination)->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($file['id']), ['alt' => 'media']);
            if (! $response->successful()) throw new RuntimeException('Không tải được bản sao lưu từ Google Drive (HTTP '.$response->status().').');
            return;
        }
        if ($connection->provider === 'onedrive') {
            $file = $this->oneDriveFileByName($connection, $filename);
            $response = $this->graphApi($connection)->withOptions(['progress' => $this->transferProgress($progress, 'Đang tải bản sao lưu từ OneDrive.')])->sink($destination)->get('https://graph.microsoft.com/v1.0/me/drive/items/'.rawurlencode($file['id']).'/content');
            if (! $response->successful()) throw new RuntimeException('Không tải được bản sao lưu từ OneDrive (HTTP '.$response->status().').');
            return;
        }
        throw new RuntimeException('Dịch vụ lưu trữ đám mây không được hỗ trợ.');
    }

    public function delete(BackupCloudConnection $connection, string $filename): void
    {
        $this->assertSupportedProvider($connection);
        $this->assertBackupName($filename);
        if ($connection->provider === 'google_drive') {
            $file = $this->googleFileByName($connection, $filename);
            $this->googleApi($connection)->delete('https://www.googleapis.com/drive/v3/files/'.rawurlencode($file['id']))->throw();
            return;
        }
        if ($connection->provider === 'onedrive') {
            $file = $this->oneDriveFileByName($connection, $filename);
            $this->graphApi($connection)->delete('https://graph.microsoft.com/v1.0/me/drive/items/'.rawurlencode($file['id']))->throw();
            return;
        }
        throw new RuntimeException('Dịch vụ lưu trữ đám mây không được hỗ trợ.');
    }

    private function uploadGoogleDrive(BackupCloudConnection $connection, string $localPath, string $filename, ?callable $progress): void
    {
        $size = filesize($localPath);
        $folderId = $this->googleFolderId($connection);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $this->googleApi($connection)->withHeaders([
                'X-Upload-Content-Type' => 'application/zip', 'X-Upload-Content-Length' => (string) $size,
            ])->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', [
                'name' => $filename, 'mimeType' => 'application/zip', 'parents' => [$folderId],
            ])->throw();
            $uploadUrl = $response->header('Location');
            if (! $uploadUrl) throw new RuntimeException('Google Drive không trả URL tải lên theo phiên.');

            try {
                $this->uploadChunks($uploadUrl, $localPath, $size, 8 * 1024 * 1024, 'google_drive', $progress);
                return;
            } catch (RuntimeException $exception) {
                // Google requires a fresh resumable session after any 4xx response.
                if (str_contains($exception->getMessage(), 'storageQuotaExceeded')) {
                    throw new RuntimeException('Google Drive đã hết dung lượng lưu trữ. Hãy giải phóng dung lượng trong Drive hoặc kết nối một tài khoản Drive khác.');
                }
                if ($attempt > 0 || ! preg_match('/HTTP 4\d{2}/', $exception->getMessage())) throw $exception;
                $progress?->__invoke(0, 'Phiên tải lên Google Drive hết hạn; đang tạo phiên mới để thử lại.');
            }
        }
    }

    private function uploadOneDrive(BackupCloudConnection $connection, string $localPath, string $filename, ?callable $progress): void
    {
        $size = filesize($localPath);
        $url = 'https://graph.microsoft.com/v1.0/me/drive/items/'.rawurlencode($this->oneDriveFolderId($connection)).':/'.rawurlencode($filename).':/createUploadSession';
        $response = $this->graphApi($connection)->post($url, ['item' => [
            '@microsoft.graph.conflictBehavior' => 'replace', 'name' => $filename,
        ]])->throw()->json();
        $uploadUrl = $response['uploadUrl'] ?? null;
        if (! $uploadUrl) throw new RuntimeException('OneDrive không trả URL tải lên theo phiên.');
        $this->uploadChunks($uploadUrl, $localPath, $size, 10 * 1024 * 1024, 'onedrive', $progress);
    }

    private function uploadChunks(string $uploadUrl, string $localPath, int $size, int $chunkSize, string $provider, ?callable $progress): void
    {
        $stream = fopen($localPath, 'rb');
        if (! $stream) throw new RuntimeException('Không thể mở tệp sao lưu để tải lên.');
        try {
            $offset = 0;
            $noProgressRetries = 0;
            do {
                $chunk = fread($stream, $chunkSize);
                if ($chunk === false || ($chunk === '' && $offset < $size)) throw new RuntimeException('Không đọc được phần tiếp theo của tệp sao lưu.');
                $length = strlen($chunk);
                $end = $offset + $length - 1;
                $response = Http::connectTimeout(15)->timeout(300)->withHeaders([
                    'Content-Length' => (string) $length, 'Content-Range' => "bytes {$offset}-{$end}/{$size}",
                ])->withBody($chunk, 'application/octet-stream')->put($uploadUrl);
                $isFinal = $end + 1 >= $size;
                $expectedStatus = $provider === 'google_drive' ? [200, 201, 308] : [200, 201, 202];
                if (! in_array($response->status(), $expectedStatus, true)) {
                    $error = $response->json();
                    $reason = data_get($error, 'error.errors.0.reason') ?: data_get($error, 'error.code');
                    $message = data_get($error, 'error.message') ?: data_get($error, 'error_description');
                    $details = collect([$reason, $message])->filter()->unique()->implode(': ');
                    if ($provider === 'google_drive' && $reason === 'storageQuotaExceeded') {
                        throw new RuntimeException('Google Drive đã hết dung lượng lưu trữ (storageQuotaExceeded). Hãy giải phóng dung lượng trong Drive hoặc kết nối một tài khoản Drive khác.');
                    }
                    throw new RuntimeException('Dịch vụ đám mây từ chối một phần tải lên (HTTP '.$response->status().')'.($details !== '' ? ': '.mb_substr($details, 0, 400) : '').'.');
                }
                $nextOffset = $offset + $length;
                if ($provider === 'google_drive' && $response->status() === 308) {
                    if (preg_match('/bytes=\d+-(\d+)/', (string) $response->header('Range'), $range)) {
                        $nextOffset = (int) $range[1] + 1;
                    } else {
                        $nextOffset = $offset;
                    }
                }
                if ($nextOffset < $offset || $nextOffset > $size) throw new RuntimeException('Dịch vụ đám mây trả về vị trí tải lên không hợp lệ.');
                if ($nextOffset === $offset) {
                    if (++$noProgressRetries > 3) throw new RuntimeException('Google Drive chưa nhận được dữ liệu tải lên sau nhiều lần thử.');
                } else {
                    $noProgressRetries = 0;
                }
                $offset = $nextOffset;
                if ($offset < $size && fseek($stream, $offset) !== 0) throw new RuntimeException('Không thể tiếp tục đọc tệp sao lưu để tải lên.');
                if ($progress) $progress((int) floor($offset / max(1, $size) * 100), 'Đang tải bản sao lưu lên '.$provider.' ('.number_format($offset).' / '.number_format($size).' byte).');
                if ($isFinal && $provider === 'google_drive' && ! in_array($response->status(), [200, 201], true)) {
                    throw new RuntimeException('Google Drive chưa hoàn tất tải lên tệp sao lưu.');
                }
                if ($isFinal && $provider === 'onedrive' && ! in_array($response->status(), [200, 201], true)) {
                    throw new RuntimeException('OneDrive chưa hoàn tất tải lên tệp sao lưu.');
                }
            } while ($offset < $size);
        } finally {
            fclose($stream);
        }
    }

    private function googleFolderId(BackupCloudConnection $connection): string
    {
        $config = $connection->configuration;
        if (! empty($config['folder_id'])) return $config['folder_id'];
        $response = $this->googleApi($connection)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "name = 'QLBH Backups' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'pageSize' => 100, 'fields' => 'files(id,name)',
        ])->throw()->json();
        $folderId = $response['files'][0]['id'] ?? null;
        if (! $folderId) {
            $folderId = $this->googleApi($connection)->post('https://www.googleapis.com/drive/v3/files', [
                'name' => 'QLBH Backups', 'mimeType' => 'application/vnd.google-apps.folder',
            ])->throw()->json('id');
        }
        if (! $folderId) throw new RuntimeException('Không tạo được thư mục QLBH Backups trong Google Drive.');
        $config['folder_id'] = $folderId;
        $connection->configuration = $config;
        if ($connection->exists) $connection->save();
        return $folderId;
    }

    private function googleFileByName(BackupCloudConnection $connection, string $filename): array
    {
        $folderId = $this->googleFolderId($connection);
        $response = $this->googleApi($connection)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "'{$folderId}' in parents and name = '{$filename}' and trashed = false", 'pageSize' => 100,
            'fields' => 'files(id,name,size,modifiedTime)', 'orderBy' => 'modifiedTime desc',
        ])->throw()->json();
        return $response['files'][0] ?? throw new RuntimeException('Không tìm thấy bản sao lưu trên Google Drive.');
    }

    private function oneDriveFileByName(BackupCloudConnection $connection, string $filename): array
    {
        $url = 'https://graph.microsoft.com/v1.0/me/drive/items/'.rawurlencode($this->oneDriveFolderId($connection)).'/children?'.http_build_query(['$select' => 'id,name,size,lastModifiedDateTime', '$top' => 200]);
        while ($url) {
            $response = $this->graphApi($connection)->get($url)->throw()->json();
            foreach ($response['value'] ?? [] as $file) if (($file['name'] ?? null) === $filename) return $file;
            $url = $response['@odata.nextLink'] ?? null;
        }
        throw new RuntimeException('Không tìm thấy bản sao lưu trên OneDrive.');
    }

    private function oneDriveFolderId(BackupCloudConnection $connection): string
    {
        $config = $connection->configuration;
        if (! empty($config['folder_id'])) return $config['folder_id'];
        $folder = $this->graphApi($connection)->get('https://graph.microsoft.com/v1.0/me/drive/special/approot')->throw()->json();
        if (empty($folder['id'])) throw new RuntimeException('Không tìm thấy thư mục ứng dụng trên OneDrive.');
        $config['folder_id'] = $folder['id'];
        $connection->configuration = $config;
        if ($connection->exists) $connection->save();
        return $folder['id'];
    }

    private function googleApi(BackupCloudConnection $connection)
    {
        return Http::connectTimeout(15)->timeout(300)->withToken($this->accessToken($connection));
    }

    private function graphApi(BackupCloudConnection $connection)
    {
        return Http::connectTimeout(15)->timeout(300)->withToken($this->accessToken($connection));
    }

    private function accessToken(BackupCloudConnection $connection): string
    {
        $config = $connection->configuration;
        if (! empty($config['access_token']) && (int) ($config['token_expires_at'] ?? 0) > now()->addMinute()->timestamp) return $config['access_token'];

        $provider = $connection->provider === 'google_drive' ? 'google_drive' : 'microsoft_onedrive';
        $url = $connection->provider === 'google_drive'
            ? 'https://oauth2.googleapis.com/token'
            : 'https://login.microsoftonline.com/'.rawurlencode(config('services.microsoft_onedrive.tenant', 'common')).'/oauth2/v2.0/token';
        $response = Http::asForm()->timeout(30)->post($url, [
            'client_id' => config("services.{$provider}.client_id"),
            'client_secret' => config("services.{$provider}.client_secret"),
            'refresh_token' => $config['refresh_token'], 'grant_type' => 'refresh_token',
            ...($connection->provider === 'onedrive' ? ['scope' => 'offline_access Files.ReadWrite'] : []),
        ]);
        if (! $response->successful()) {
            $details = $response->json('error_description') ?: $response->json('error') ?: $response->body();
            throw new RuntimeException('Không làm mới được token '.$connection->name.' (HTTP '.$response->status().'): '.mb_substr((string) $details, 0, 500).'. Hãy kết nối lại dịch vụ.');
        }
        $tokens = $response->json();
        $config['access_token'] = $tokens['access_token'];
        $config['refresh_token'] = $tokens['refresh_token'] ?? $config['refresh_token'];
        $config['token_expires_at'] = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))->timestamp;
        $connection->configuration = $config;
        $connection->save();
        return $config['access_token'];
    }

    private function transferProgress(?callable $progress, string $message): callable
    {
        return static function (int $downloadTotal, int $downloaded, int $uploadTotal, int $uploaded) use ($progress, $message): void {
            $total = $uploadTotal > 0 ? $uploadTotal : $downloadTotal;
            $current = $uploadTotal > 0 ? $uploaded : $downloaded;
            if ($progress && $total > 0) $progress((int) min(100, floor($current / $total * 100)), $message.' ('.number_format($current).' / '.number_format($total).' byte).');
        };
    }

    private function isBackupName(string $name): bool
    {
        return (bool) preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-\d{6})?\.zip$/', $name);
    }

    private function assertBackupName(string $name): void
    {
        if (! $this->isBackupName($name)) throw new RuntimeException('Tên bản sao lưu không hợp lệ.');
    }

    private function assertSupportedProvider(BackupCloudConnection $connection): void
    {
        if (! in_array($connection->provider, BackupCloudConnection::SUPPORTED_PROVIDERS, true)) {
            throw new RuntimeException('Chỉ hỗ trợ Google Drive và OneDrive cho kho sao lưu đám mây.');
        }
    }
}
