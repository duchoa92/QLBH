<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Tình trạng ổ đĩa chứa website, để hiện cảnh báo trên màn hình.
 *
 * - Dung lượng: PHP tự đọc (mọi hệ điều hành).
 * - Lỗi ổ đĩa (SMART, lỗi đọc/ghi, bị chuyển sang chỉ đọc): do scripts/disk-health.sh (root, cron 15 phút)
 *   ghi vào storage/app/disk-health.json vì tiến trình web không có quyền đọc các thông tin này.
 */
class DiskHealth
{
    public const WARNING_PERCENT = 85;
    public const DANGER_PERCENT = 95;
    public const DANGER_FREE_BYTES = 2 * 1024 ** 3;

    /** @return array{level: string, used_percent: float|null, free_gb: float|null, total_gb: float|null, messages: array<int, string>, backup_now: bool} */
    public static function report(): array
    {
        return Cache::remember('disk-health-report', 60, fn () => self::build());
    }

    private static function build(): array
    {
        $level = 'ok';
        $messages = [];
        $backupNow = false;
        $usedPercent = $freeGb = $totalGb = null;

        try {
            $path = base_path();
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            if ($free !== false && $total !== false && $total > 0) {
                $usedPercent = round((1 - $free / $total) * 100, 1);
                $freeGb = round($free / 1024 ** 3, 1);
                $totalGb = round($total / 1024 ** 3, 1);

                if ($usedPercent >= self::DANGER_PERCENT || $free < self::DANGER_FREE_BYTES) {
                    $level = 'danger';
                    $messages[] = "Ổ đĩa sắp hết chỗ: đã dùng {$usedPercent}%, còn trống {$freeGb} GB. Hãy giải phóng dung lượng và sao lưu ngay.";
                } elseif ($usedPercent >= self::WARNING_PERCENT) {
                    $level = 'warning';
                    $messages[] = "Ổ đĩa gần đầy: đã dùng {$usedPercent}%, còn trống {$freeGb} GB.";
                }
            }
        } catch (Throwable) {
            // Không đọc được dung lượng: bỏ qua, không làm hỏng trang.
        }

        $health = self::readHealthFile();
        if ($health) {
            if (($health['smart'] ?? '') === 'failed') {
                $level = 'danger';
                $backupNow = true;
                $messages[] = 'Ổ đĩa báo hỏng (SMART: FAILED). Sao lưu dữ liệu ngay và thay ổ đĩa.';
            }
            if (($health['read_only'] ?? false) === true) {
                $level = 'danger';
                $backupNow = true;
                $messages[] = 'Ổ đĩa đang bị chuyển sang chế độ chỉ đọc do lỗi hệ thống tệp. Sao lưu ngay và kiểm tra ổ đĩa.';
            }
            $ioErrors = (int) ($health['io_errors'] ?? 0);
            if ($ioErrors > 0) {
                if ($level !== 'danger') {
                    $level = 'warning';
                }
                $backupNow = true;
                $messages[] = "Phát hiện {$ioErrors} lỗi đọc/ghi ổ đĩa trong 24 giờ qua. Nên sao lưu ngay và kiểm tra ổ đĩa.";
            }
        }

        if ($level === 'danger') {
            $backupNow = true;
        }

        return [
            'level' => $level,
            'used_percent' => $usedPercent,
            'free_gb' => $freeGb,
            'total_gb' => $totalGb,
            'messages' => $messages,
            'backup_now' => $backupNow,
        ];
    }

    /** Kết quả của scripts/disk-health.sh; bỏ qua nếu quá cũ (quá 6 giờ) vì có thể script đã ngừng chạy. */
    private static function readHealthFile(): ?array
    {
        $path = storage_path('app/disk-health.json');
        if (! is_file($path) || ! is_readable($path) || filemtime($path) < time() - 6 * 3600) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }
}
