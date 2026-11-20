<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** Stores consented face pictures on Laravel's private disk, never /public. */
class FaceImageStorage
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    /** @throws InvalidArgumentException|RuntimeException */
    public function storeDataUrl(string $value, string $directory): string
    {
        if (! preg_match('#^data:image/(?:jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#D', $value, $matches)) {
            throw new InvalidArgumentException('Ảnh khuôn mặt không đúng định dạng JPEG, PNG hoặc WebP.');
        }

        $bytes = base64_decode($matches[1], true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Ảnh khuôn mặt không hợp lệ hoặc vượt quá 8 MB.');
        }

        $size = @getimagesizefromstring($bytes);
        $mime = is_array($size) ? strtolower((string) ($size['mime'] ?? '')) : '';
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidArgumentException('Chỉ nhận ảnh JPEG, PNG hoặc WebP.'),
        };
        $width = (int) ($size[0] ?? 0);
        $height = (int) ($size[1] ?? 0);
        if ($width < 32 || $height < 32 || $width * $height > 12_000_000) {
            throw new InvalidArgumentException('Kích thước ảnh khuôn mặt không hợp lệ.');
        }

        $path = trim($directory, '/').'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('Không thể lưu ảnh khuôn mặt vào vùng lưu trữ riêng tư.');
        }

        return $path;
    }

    /** @param array<int, string|null> $paths */
    public function deleteMany(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths)));
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }
}
