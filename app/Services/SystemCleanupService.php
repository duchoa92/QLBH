<?php

namespace App\Services;

use App\Models\CustomerImage;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\RepairImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class SystemCleanupService
{
    private const DIRECTORIES = ['products', 'customers', 'repairs', 'avatars', 'product-imei-info'];

    public function scan(): array
    {
        $disk = Storage::disk('public');
        $referenced = $this->referencedPaths();
        $files = collect(self::DIRECTORIES)->flatMap(fn (string $directory) => $disk->allFiles($directory));

        $orphans = $files
            ->filter(fn (string $path) => ! isset($referenced[$this->key($path)]))
            ->map(fn (string $path) => [
                'path' => $path,
                'size' => (int) $disk->size($path),
                'last_modified' => (int) $disk->lastModified($path),
            ])
            ->sortBy('path')
            ->values();

        return [
            'files' => $orphans->all(),
            'count' => $orphans->count(),
            'bytes' => (int) $orphans->sum('size'),
            'scanned_directories' => self::DIRECTORIES,
        ];
    }

    /** Delete only files still unreferenced at confirmation time. */
    public function delete(array $paths): array
    {
        $disk = Storage::disk('public');
        $scannedCandidates = array_fill_keys(array_map(fn (array $file) => $this->key($file['path']), $this->scan()['files']), true);
        $referenced = $this->referencedPaths();
        $deleted = 0;
        $bytes = 0;

        foreach (array_unique($paths) as $path) {
            $key = is_string($path) ? $this->key($path) : '';
            if (! is_string($path) || ! $this->isAllowedPath($path) || ! isset($scannedCandidates[$key]) || isset($referenced[$key]) || ! $disk->exists($path)) {
                continue;
            }

            $size = (int) $disk->size($path);
            if ($disk->delete($path)) {
                $deleted++;
                $bytes += $size;
            }
        }

        return ['deleted' => $deleted, 'bytes' => $bytes];
    }

    private function referencedPaths(): array
    {
        $paths = [];
        Product::withTrashed()->whereNotNull('image')->pluck('image')->each(function ($path) use (&$paths) { $this->addPath($paths, $path); });
        CustomerImage::query()->pluck('path')->each(function ($path) use (&$paths) { $this->addPath($paths, $path); });
        RepairImage::query()->pluck('image_path')->each(function ($path) use (&$paths) { $this->addPath($paths, $path); });
        User::query()->whereNotNull('avatar')->pluck('avatar')->each(function ($path) use (&$paths) { $this->addPath($paths, $path); });
        ProductImei::withTrashed()->pluck('extra_info')->each(function ($info) use (&$paths) {
            if (is_string($info)) $info = json_decode($info, true);
            if (is_array($info)) $this->collectImagePaths($paths, $info);
        });

        return $paths;
    }

    private function collectImagePaths(array &$paths, array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $this->collectImagePaths($paths, $value);
            } elseif (is_string($value) && (str_contains(strtolower((string) $key), 'image') || str_contains(strtolower((string) $key), 'photo'))) {
                $this->addPath($paths, $value);
            }
        }
    }

    private function addPath(array &$paths, mixed $path): void
    {
        if (is_string($path) && $path !== '') $paths[$this->key($path)] = true;
    }

    private function key(string $path): string
    {
        return strtolower(str_replace('\\', '/', ltrim($path, '/')));
    }

    private function isAllowedPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', ltrim($path, '/'));
        if (str_contains($normalized, '../') || str_contains($normalized, '\0')) return false;
        // Backup content is never eligible for this cleanup, regardless of disk or path shape.
        if (preg_match('~(?:^|/)backups?(?:/|$)~i', $normalized)) return false;

        foreach (self::DIRECTORIES as $directory) {
            if (str_starts_with($normalized, $directory.'/')) return true;
        }

        return false;
    }
}
