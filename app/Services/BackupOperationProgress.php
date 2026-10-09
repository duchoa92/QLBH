<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class BackupOperationProgress
{
    public function update(int $userId, string $id, int $percent, string $message, string $state = 'running'): void
    {
        if (! preg_match('/^[a-f0-9-]{36}$/i', $id)) return;
        $directory = storage_path('app/backup-progress');
        File::ensureDirectoryExists($directory);
        File::put($this->path($directory, $userId, $id), json_encode([
            'percent' => max(0, min(100, $percent)),
            'message' => $message,
            'state' => $state,
            'updated_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR), true);
    }

    public function get(int $userId, string $id): array
    {
        if (! preg_match('/^[a-f0-9-]{36}$/i', $id)) abort(404);
        $path = $this->path(storage_path('app/backup-progress'), $userId, $id);
        abort_unless(is_file($path), 404);
        return json_decode(File::get($path), true) ?: ['percent' => 0, 'message' => 'Đang chờ máy chủ...', 'state' => 'running'];
    }

    private function path(string $directory, int $userId, string $id): string
    {
        return $directory.DIRECTORY_SEPARATOR.$userId.'_'.$id.'.json';
    }
}
