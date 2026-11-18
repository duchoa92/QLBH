<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Các ngưỡng tự học khuôn mặt, chỉnh ở Cài đặt → Khách hàng.
 * Mỗi thông số: [mặc định, nhỏ nhất, lớn nhất].
 */
final class FaceTuning
{
    public const NUMERIC = [
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

    /** @return array<string, int|bool> toàn bộ thông số hiện tại (đã chặn trong khoảng cho phép). */
    public static function all(): array
    {
        $values = [];
        foreach (array_keys(self::NUMERIC) as $key) {
            $values[$key] = self::int($key);
        }
        $values['face_auto_learn_enabled'] = self::autoLearnEnabled();
        return $values;
    }

    /** @return array<string, array<int, string>> luật kiểm tra dữ liệu gửi lên từ trang Cài đặt. */
    public static function rules(): array
    {
        $rules = ['face_auto_learn_enabled' => ['sometimes', 'boolean']];
        foreach (self::NUMERIC as $key => [, $min, $max]) {
            $rules[$key] = ['sometimes', 'integer', 'min:'.$min, 'max:'.$max];
        }

        return $rules;
    }

}
