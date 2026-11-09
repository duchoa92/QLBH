<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kho ảnh tạm cho luồng nhập sản phẩm.
 *
 * Trình duyệt tải ảnh lên theo từng đợt nhỏ (tránh giới hạn max_file_uploads /
 * post_max_size của PHP), sau đó bước kiểm tra và bước nhập chỉ gửi kèm `token`.
 */
class ProductImportImageStore
{
    private const ROOT = 'import-tmp';
    private const MANIFEST = 'manifest.json';
    private const MAX_AGE_SECONDS = 86400;

    private function disk()
    {
        return Storage::disk('local');
    }

    public function newToken(): string
    {
        return (string) Str::uuid();
    }

    /** @param array<int, UploadedFile> $files */
    public function add(string $token, array $files, int $userId): int
    {
        $this->assertToken($token);
        $this->purgeOld();

        $manifest = $this->readManifest($token) ?? ['user_id' => $userId, 'files' => []];

        if ((int) $manifest['user_id'] !== $userId) {
            throw new ProductImportException('Phiên tải ảnh không hợp lệ.');
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
            $stored = Str::lower(Str::random(20)) . '.' . $extension;

            $this->disk()->putFileAs(self::ROOT . '/' . $token, $file, $stored);

            $manifest['files'][] = [
                'name' => $file->getClientOriginalName(),
                'stored' => $stored,
            ];
        }

        $this->disk()->put($this->manifestPath($token), json_encode($manifest, JSON_UNESCAPED_UNICODE));

        return count($manifest['files']);
    }

    /** @return array<int, array{name: string, path: string}> */
    public function resolve(string $token, int $userId): array
    {
        $this->assertToken($token);
        $manifest = $this->readManifest($token);

        if (! $manifest || (int) $manifest['user_id'] !== $userId) {
            throw new ProductImportException('Phiên tải ảnh đã hết hạn, vui lòng chọn lại ảnh.');
        }

        $images = [];

        foreach ($manifest['files'] as $item) {
            $relative = self::ROOT . '/' . $token . '/' . $item['stored'];

            if ($this->disk()->exists($relative)) {
                $images[] = ['name' => (string) $item['name'], 'path' => $this->disk()->path($relative)];
            }
        }

        return $images;
    }

    public function forget(string $token): void
    {
        if (! $this->validToken($token)) {
            return;
        }

        try {
            $this->disk()->deleteDirectory(self::ROOT . '/' . $token);
        } catch (Throwable) {
            // Dọn dẹp thất bại không ảnh hưởng kết quả nhập.
        }
    }

    /** Xóa các phiên ảnh quá 24 giờ chưa được dùng. */
    public function purgeOld(): void
    {
        try {
            foreach ($this->disk()->directories(self::ROOT) as $directory) {
                $manifest = $directory . '/' . self::MANIFEST;
                $modified = $this->disk()->exists($manifest) ? $this->disk()->lastModified($manifest) : 0;

                if ($modified < now()->timestamp - self::MAX_AGE_SECONDS) {
                    $this->disk()->deleteDirectory($directory);
                }
            }
        } catch (Throwable) {
            // Bỏ qua: sẽ dọn ở lần sau.
        }
    }

    private function manifestPath(string $token): string
    {
        return self::ROOT . '/' . $token . '/' . self::MANIFEST;
    }

    private function readManifest(string $token): ?array
    {
        if (! $this->disk()->exists($this->manifestPath($token))) {
            return null;
        }

        $data = json_decode((string) $this->disk()->get($this->manifestPath($token)), true);

        return is_array($data) && isset($data['user_id'], $data['files']) ? $data : null;
    }

    private function validToken(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token);
    }

    private function assertToken(string $token): void
    {
        if (! $this->validToken($token)) {
            throw new ProductImportException('Mã phiên tải ảnh không hợp lệ.');
        }
    }
}
