<?php

declare(strict_types=1);

namespace App\Support;

/**
 * So khớp vector khuôn mặt do Human (@vladmandic/human) tạo ra ở trình duyệt và ở worker camera cửa.
 *
 * Độ giống tính giống hàm similarity của Human (khoảng cách Euclid, chuẩn hóa về 0..1) nên ngưỡng khuyến nghị của
 * Human áp dụng được. Hai vector khác độ dài (ví dụ dữ liệu cũ từ mô hình khác) luôn có độ giống 0.
 */
final class FaceMatcher
{
    /** Độ dài vector chấp nhận được (Human cho vector khoảng 1024 số tùy mô hình; không cố định ở một con số). */
    public const MIN_SIZE = 64;
    public const MAX_SIZE = 2048;

    /** Giới hạn giá trị từng phần tử, chỉ để chặn dữ liệu rác. */
    public const VALUE_LIMIT = 1000;

    private const MULTIPLIER = 25.0;
    private const MIN = 0.2;
    private const MAX = 0.8;

    /**
     * @param  array<int, float|int|string>  $a
     * @param  array<int, float|int|string>  $b
     */
    public static function similarity(array $a, array $b): float
    {
        $count = count($a);
        if ($count === 0 || $count !== count($b)) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $diff = (float) $a[$i] - (float) $b[$i];
            $sum += $diff * $diff;
        }
        if ($sum === 0.0) {
            return 1.0;
        }

        $normalized = (1 - sqrt(self::MULTIPLIER * $sum) / 100 - self::MIN) / (self::MAX - self::MIN);

        return max(0.0, min(1.0, $normalized));
    }

    /** @param array<int, float|int|string> $a @param array<int, float|int|string>|null $b */
    public static function isSamePerson(array $a, ?array $b): bool
    {
        return $b !== null && self::similarity($a, $b) >= (float) config('face.match_similarity', 0.5);
    }
}
