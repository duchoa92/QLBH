<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/** Lịch chạy nền (cron / Scheduled Task) có đang chạy không? Dựa vào nhịp tim ghi mỗi phút (routes/console.php). */
class SchedulerHealth
{
    /** Quá số phút này không có nhịp tim thì coi là lịch chạy nền đã dừng. */
    public const STALE_MINUTES = 5;

    /** @return array{last_run: string|null, minutes_ago: int|null, backup_enabled: bool}|null null = bình thường */
    public static function problem(): ?array
    {
        try {
            $last = Cache::get('scheduler.heartbeat');
            $installedAt = @filemtime(storage_path('app/installed')) ?: null;

            if ($last === null) {
                // Mới cài xong: cho cron vài phút để chạy lần đầu.
                if ($installedAt !== null && $installedAt > time() - 600) {
                    return null;
                }
                $minutes = null;
            } else {
                $minutes = (int) floor((time() - (int) $last) / 60);
                if ($minutes < self::STALE_MINUTES) {
                    return null;
                }
            }

            return [
                'last_run' => $last ? date('d/m/Y H:i', (int) $last) : null,
                'minutes_ago' => $minutes,
                'backup_enabled' => filter_var(setting('backup_enabled', false), FILTER_VALIDATE_BOOLEAN),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
