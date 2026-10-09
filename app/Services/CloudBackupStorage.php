<?php

namespace App\Services;

use App\Models\BackupCloudConnection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;

class CloudBackupStorage
{
    public function test(BackupCloudConnection $connection): void
    {
        if ($connection->provider === 'google_drive') {
            $folderId = $this->googleFolderId($connection);
            $this->googleApi($connection)->get('https://www.googleapis.com/drive/v3/files', ['q' => "'{$folderId}' in parents and trashed = false", 'pageSize' => 1, 'fields' => 'files(id)'])->throw();
            return;
        }
        if ($connection->provider === 'onedrive') {
            $this->oneDriveFolderId($connection);
            return;
        }
        if ($connection->provider === 's3') {
            $disk = $this->s3($connection);
            $disk->files($this->prefix($connection));
            return;
        }

        $this->ensureWebdavPath($connection);
        $response = $this->webdav($connection)->withHeaders(['Depth' => '0'])->send('PROPFIND', $this->webdavUrl($connection), [
            'body' => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
        ]);
        if (! $response->successful() && $response->status() !== 207) {
            throw new RuntimeException('WebDAV trả về HTTP '.$response->status().'. Kiểm tra URL, tài khoản và quyền truy cập thư mục.');
        }
    }

    public function upload(BackupCloudConnection $connection, string $localPath, string $filename, ?callable $progress = null): void
    {
        $this->assertBackupName($filename);
        $progress?->__invoke(5, 'Đang kết nối kho đám mây '.$connection->name.'.');
        if ($connection->provider === 's3') {
            $stream = fopen($localPath, 'rb');
            if (! $stream) throw new RuntimeException('Không thể mở tệp sao lưu để tải lên.');
            try {
                if (! $this->s3($connection, $progress)->writeStream($this->objectPath($connection, $filename), $stream, ['visibility' => 'private'])) {
                    throw new RuntimeException('Không thể tải bản sao lưu lên kho S3.');
                }
            } finally {
                fclose($stream);
            }
        } elseif ($connection->provider === 'webdav') {
            $this->ensureWebdavPath($connection);
            $stream = fopen($localPath, 'rb');
            if (! $stream) throw new RuntimeException('Không thể mở tệp sao lưu để tải lên.');
            try {
                $response = $this->webdav($connection)->withOptions(['progress' => $this->transferProgress($progress, 'Đang tải bản sao lưu lên WebDAV.')])->withBody($stream, 'application/zip')->send('PUT', $this->webdavUrl($connection, $filename));
                if (! $response->successful() && $response->status() !== 201 && $response->status() !== 204) {
                    throw new RuntimeException('WebDAV không nhận tệp sao lưu (HTTP '.$response->status().').');
                }
            } finally {
                fclose($stream);
            }
        } elseif ($connection->provider === 'google_drive') {
            $this->uploadGoogleDrive($connection, $localPath, $filename, $progress);
        } else {
            $this->uploadOneDrive($connection, $localPath, $filename, $progress);
        }
        $progress?->__invoke(100, 'Đã tải bản sao lưu lên '.$connection->name.'.');
    }

    public function backups(BackupCloudConnection $connection): array
    {
        if ($connection->provider === 's3') {
            $disk = $this->s3($connection);
            $files = [];
            foreach ($disk->files($this->prefix($connection)) as $path) {
                $name = basename($path);
                if (! $this->isBackupName($name)) continue;
                $files[] = ['name' => $name, 'size' => $disk->size($path), 'created_at' => date('Y-m-d H:i:s', $disk->lastModified($path))];
            }
            return $files;
        }

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

        $response = $this->webdav($connection)->withHeaders(['Depth' => '1'])->send('PROPFIND', $this->webdavUrl($connection), [
            'body' => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/><d:getlastmodified/></d:prop></d:propfind>',
        ]);
        if (! $response->successful() && $response->status() !== 207) throw new RuntimeException('Không đọc được danh sách WebDAV (HTTP '.$response->status().').');
        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (! $xml) throw new RuntimeException('WebDAV trả về danh sách tệp không hợp lệ.');
        $files = [];
        foreach ($xml->xpath('//*[local-name()="response"]') ?: [] as $item) {
            $href = (string) ($item->xpath('.//*[local-name()="href"]')[0] ?? '');
            $name = rawurldecode(basename((string) parse_url($href, PHP_URL_PATH)));
            if (! $this->isBackupName($name)) continue;
            $size = (int) ($item->xpath('.//*[local-name()="getcontentlength"]')[0] ?? 0);
            $modified = (string) ($item->xpath('.//*[local-name()="getlastmodified"]')[0] ?? '');
            $files[] = ['name' => $name, 'size' => $size, 'created_at' => $modified ? date('Y-m-d H:i:s', strtotime($modified)) : null];
        }
        return $files;
    }

    public function download(BackupCloudConnection $connection, string $filename, string $destination, ?callable $progress = null): void
    {
        $this->assertBackupName($filename);
        if ($connection->provider === 's3') {
            $disk = $this->s3($connection);
            $remotePath = $this->objectPath($connection, $filename);
            $size = max(1, $disk->size($remotePath));
            $disk = $this->s3($connection, $progress);
            $stream = $disk->readStream($remotePath);
            if (! is_resource($stream)) throw new RuntimeException('Không mở được bản sao lưu trên S3.');
            try {
                $target = fopen($destination, 'wb');
                if (! $target) throw new RuntimeException('Không thể tạo tệp tạm để kiểm tra bản sao lưu.');
                $downloaded = 0;
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false || $chunk === '') break;
                    $downloaded += strlen($chunk);
                    fwrite($target, $chunk);
                    if ($progress) $progress((int) min(100, floor($downloaded / $size * 100)), 'Đang tải bản sao lưu từ kho S3.');
                }
                fclose($target);
            } finally {
                fclose($stream);
            }
            return;
        }
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
        $response = $this->webdav($connection)->withOptions(['progress' => $this->transferProgress($progress, 'Đang tải bản sao lưu từ WebDAV.')])->sink($destination)->get($this->webdavUrl($connection, $filename));
        if (! $response->successful()) throw new RuntimeException('Không tải được bản sao lưu từ WebDAV (HTTP '.$response->status().').');
    }

    public function delete(BackupCloudConnection $connection, string $filename): void
    {
        $this->assertBackupName($filename);
        if ($connection->provider === 's3') {
            $this->s3($connection)->delete($this->objectPath($connection, $filename));
            return;
        }
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
        $response = $this->webdav($connection)->delete($this->webdavUrl($connection, $filename));
        if (! $response->successful() && $response->status() !== 204) throw new RuntimeException('Không xóa được bản sao lưu trên WebDAV (HTTP '.$response->status().').');
    }

    private function uploadGoogleDrive(BackupCloudConnection $connection, string $localPath, string $filename, ?callable $progress): void
    {
        $size = filesize($localPath);
        $folderId = $this->googleFolderId($connection);
        $response = $this->googleApi($connection)->withHeaders([
            'X-Upload-Content-Type' => 'application/zip', 'X-Upload-Content-Length' => (string) $size,
        ])->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', [
            'name' => $filename, 'mimeType' => 'application/zip', 'parents' => [$folderId],
        ])->throw();
        $uploadUrl = $response->header('Location');
        if (! $uploadUrl) throw new RuntimeException('Google Drive không trả URL tải lên theo phiên.');
        $this->uploadChunks($uploadUrl, $localPath, $size, 8 * 1024 * 1024, 'google_drive', $progress);
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
                    throw new RuntimeException('Dịch vụ đám mây từ chối một phần tải lên (HTTP '.$response->status().').');
                }
                $offset += $length;
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
        if (! $response->successful()) throw new RuntimeException('Không làm mới được token '.$connection->name.' (HTTP '.$response->status().'). Hãy kết nối lại dịch vụ.');
        $tokens = $response->json();
        $config['access_token'] = $tokens['access_token'];
        $config['refresh_token'] = $tokens['refresh_token'] ?? $config['refresh_token'];
        $config['token_expires_at'] = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))->timestamp;
        $connection->configuration = $config;
        $connection->save();
        return $config['access_token'];
    }

    private function s3(BackupCloudConnection $connection, ?callable $progress = null): FilesystemAdapter
    {
        $config = $connection->configuration;
        $http = [];
        if ($progress) $http['progress'] = $this->transferProgress($progress, 'Đang tải bản sao lưu lên S3.');
        return Storage::build([
            'driver' => 's3', 'key' => $config['key'], 'secret' => $config['secret'], 'region' => $config['region'] ?: 'auto',
            'bucket' => $config['bucket'], 'endpoint' => $config['endpoint'] ?: null,
            'use_path_style_endpoint' => (bool) ($config['path_style'] ?? false), 'throw' => true, 'http' => $http,
        ]);
    }

    private function transferProgress(?callable $progress, string $message): callable
    {
        return static function (int $downloadTotal, int $downloaded, int $uploadTotal, int $uploaded) use ($progress, $message): void {
            $total = $uploadTotal > 0 ? $uploadTotal : $downloadTotal;
            $current = $uploadTotal > 0 ? $uploaded : $downloaded;
            if ($progress && $total > 0) $progress((int) min(100, floor($current / $total * 100)), $message.' ('.number_format($current).' / '.number_format($total).' byte).');
        };
    }

    private function webdav(BackupCloudConnection $connection)
    {
        $config = $connection->configuration;
        return Http::connectTimeout(10)->timeout(300)->withBasicAuth($config['username'], $config['token'])->accept('*/*');
    }

    private function ensureWebdavPath(BackupCloudConnection $connection): void
    {
        $config = $connection->configuration;
        $segments = array_filter(explode('/', trim($config['base_path'] ?? 'backups', '/')));
        $base = rtrim($config['url'], '/');
        foreach ($segments as $segment) {
            $base .= '/'.rawurlencode($segment);
            $response = $this->webdav($connection)->send('MKCOL', $base);
            if (! $response->successful() && ! in_array($response->status(), [201, 405], true)) {
                throw new RuntimeException('Không tạo được thư mục WebDAV (HTTP '.$response->status().').');
            }
        }
    }

    private function webdavUrl(BackupCloudConnection $connection, ?string $filename = null): string
    {
        $config = $connection->configuration;
        $url = rtrim($config['url'], '/');
        foreach (array_filter(explode('/', trim($config['base_path'] ?? 'backups', '/'))) as $segment) $url .= '/'.rawurlencode($segment);
        if ($filename) $url .= '/'.rawurlencode($filename);
        return $url;
    }

    private function prefix(BackupCloudConnection $connection): string
    {
        return trim($connection->configuration['base_path'] ?? 'backups', '/');
    }

    private function objectPath(BackupCloudConnection $connection, string $filename): string
    {
        return ($this->prefix($connection) ? $this->prefix($connection).'/' : '').$filename;
    }

    private function isBackupName(string $name): bool
    {
        return (bool) preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-\d{6})?\.zip$/', $name);
    }

    private function assertBackupName(string $name): void
    {
        if (! $this->isBackupName($name)) throw new RuntimeException('Tên bản sao lưu không hợp lệ.');
    }
}
