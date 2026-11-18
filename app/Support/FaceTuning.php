<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Các thông số tinh chỉnh nhận diện khuôn mặt, chỉnh ở Cài đặt → Khách hàng (không phải sửa file .env của worker).
 * Mỗi thông số: [mặc định, nhỏ nhất, lớn nhất]. Worker đọc về qua /door-camera/config và tự áp dụng khi đổi.
 */
final class FaceTuning
{
    public const NUMERIC = [
        // Worker camera cửa
        'face_min_face_px' => [60, 30, 300],          // mặt rộng dưới số điểm ảnh này bị bỏ qua
        'face_detect_fps' => [4, 1, 15],              // số hình/giây lấy từ camera RTSP để nhận diện
        'face_detect_interval_ms' => [300, 100, 3000], // nghỉ giữa 2 lần dò khi có người
        'face_roi_left' => [0, 0, 80],                // vùng dò mặt (% khung hình)
        'face_roi_top' => [0, 0, 80],
        'face_roi_width' => [100, 10, 100],
        'face_roi_height' => [100, 10, 100],
        // Tự học khuôn mặt
        'face_auto_learn_min_percent' => [75, 60, 95],      // độ giống tối thiểu (%)
        'face_auto_learn_margin_percent' => [15, 5, 50],    // phải hơn khách giống thứ nhì ít nhất (%)
        'face_auto_learn_cooldown_minutes' => [120, 10, 1440],
    ];

    public static function int(string $key): int
    {
        [$default, $min, $max] = self::NUMERIC[$key];

        return max($min, min($max, (int) round((float) setting($key, $default))));
    }

    public static function autoLearnEnabled(): bool
    {
        return filter_var(setting('face_auto_learn_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /** Human keeps the established flow; SFace can be selected directly or run in a non-authoritative compare mode. */
    public static function cameraEngine(): string
    {
        $engine = (string) setting('face_camera_engine', 'human');

        return in_array($engine, ['human', 'sface', 'compare'], true) ? $engine : 'human';
    }

    /** @return array<string, int|bool> toàn bộ thông số hiện tại (đã chặn trong khoảng cho phép). */
    public static function all(): array
    {
        $values = [];
        foreach (array_keys(self::NUMERIC) as $key) {
            $values[$key] = self::int($key);
        }
        $values['face_auto_learn_enabled'] = self::autoLearnEnabled();
        $values['face_camera_engine'] = self::cameraEngine();

        return $values;
    }

    /** @return array<string, array<int, string>> luật kiểm tra dữ liệu gửi lên từ trang Cài đặt. */
    public static function rules(): array
    {
        $rules = [
            'face_auto_learn_enabled' => ['sometimes', 'boolean'],
            'face_camera_engine' => ['sometimes', 'in:human,sface,compare'],
        ];
        foreach (self::NUMERIC as $key => [, $min, $max]) {
            $rules[$key] = ['sometimes', 'integer', 'min:'.$min, 'max:'.$max];
        }

        return $rules;
    }

    /** Vùng dò mặt dạng tỉ lệ 0-1, hoặc null nếu dùng toàn khung hình. */
    public static function roi(): ?array
    {
        $left = self::int('face_roi_left');
        $top = self::int('face_roi_top');
        $width = min(self::int('face_roi_width'), 100 - $left);
        $height = min(self::int('face_roi_height'), 100 - $top);
        if ($left === 0 && $top === 0 && $width >= 100 && $height >= 100) {
            return null;
        }

        return ['x' => $left / 100, 'y' => $top / 100, 'w' => max(0.1, $width / 100), 'h' => max(0.1, $height / 100)];
    }

    /** Phần worker cần biết (gửi kèm /door-camera/config). */
    public static function worker(): array
    {
        return [
            'min_face_px' => self::int('face_min_face_px'),
            'detect_fps' => self::int('face_detect_fps'),
            'detect_interval_ms' => self::int('face_detect_interval_ms'),
            'detect_roi' => self::roi(),
            'camera_engine' => self::cameraEngine(),
        ];
    }
}
