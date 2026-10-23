<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class BackupOperationProgress
{
    public function update(int $userId, string $id, int $percent, string $message, string $state = 'running', ?array $report = null): void
    {
        if (! preg_match('/^[a-f0-9-]{36}$/i', $id)) return;
        $directory = storage_path('app/backup-progress');
        File::ensureDirectoryExists($directory);
        $path = $this->path($directory, $userId, $id);
        $previous = is_file($path) ? (json_decode(File::get($path), true) ?: []) : [];
        $steps = $previous['steps'] ?? [];
        $lastStep = $steps === [] ? null : $steps[count($steps) - 1];
        preg_match('/^(?:Bản cứu hộ: )?((?:Đang|Đã|Bắt đầu|Tạo|Khôi phục|Tải lên)[^:.(]+)/u', $message, $stageMatch);
        $stage = trim($stageMatch[1] ?? $message);
        $stage = preg_replace('/^Đang (?:đối chiếu bảng|so sánh dữ liệu bảng)\b.*/u', 'Đang đối chiếu dữ liệu từng bảng', $stage);
        $stage = preg_replace('/^(Đang (?:làm sạch bảng|xóa tệp đã tải lên))\b.*/u', '$1', $stage);
        $isMilestone = $lastStep === null
            || $state !== 'running'
            || ($lastStep['stage'] ?? null) !== $stage
            || ($percent - (int) ($lastStep['percent'] ?? 0)) >= 8;
        if ($isMilestone && ($lastStep['message'] ?? null) !== $message && $message !== '') {
            $steps[] = ['percent' => max(0, min(100, $percent)), 'message' => $message, 'stage' => $stage, 'at' => now()->format('H:i:s')];
            $steps = array_slice($steps, -100);
        }
        File::put($path, json_encode([
            'percent' => max(0, min(100, $percent)),
            'message' => $message,
            'state' => $state,
            'steps' => $steps,
            'report' => $report ?? ($previous['report'] ?? null),
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
