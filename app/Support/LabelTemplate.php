<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mẫu in tem IMEI/Serial lưu trong cài đặt (khóa `label_template`, dạng JSON).
 * Mọi giá trị đều được ép về khoảng hợp lệ để dữ liệu hỏng không làm vỡ giao diện in.
 */
class LabelTemplate
{
    public const DEFAULTS = [
        'layout' => 'sheet',       // sheet: nhiều tem trên 1 trang | roll: mỗi tem 1 trang (máy in tem cuộn)
        'width_mm' => 50,
        'height_mm' => 30,
        'gap_mm' => 2,
        'margin_mm' => 5,
        'border' => 'none',        // none | dashed | solid
        'align' => 'left',         // left | center
        'show_shop' => false,
        'show_name' => true,
        'show_variant' => true,
        'show_code' => true,
        'show_price' => true,
        'show_barcode' => true,
        'name_size' => 8,
        'code_size' => 7,
        'price_size' => 9,
        'barcode_height_mm' => 9,
        'footer_text' => '',
    ];

    private const LIMITS = [
        'width_mm' => [20, 150],
        'height_mm' => [10, 100],
        'gap_mm' => [0, 15],
        'margin_mm' => [0, 25],
        'name_size' => [5, 24],
        'code_size' => [5, 20],
        'price_size' => [5, 28],
        'barcode_height_mm' => [3, 40],
    ];

    /** @param array<string, mixed>|string|null $value */
    public static function normalize(array|string|null $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        $value = is_array($value) ? $value : [];
        $result = self::DEFAULTS;

        foreach (self::LIMITS as $key => [$min, $max]) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                $result[$key] = min($max, max($min, round((float) $value[$key], 1)));
            }
        }

        foreach (['layout' => ['sheet', 'roll'], 'border' => ['none', 'dashed', 'solid'], 'align' => ['left', 'center']] as $key => $allowed) {
            if (isset($value[$key]) && in_array($value[$key], $allowed, true)) {
                $result[$key] = $value[$key];
            }
        }

        foreach (['show_shop', 'show_name', 'show_variant', 'show_code', 'show_price', 'show_barcode'] as $key) {
            if (array_key_exists($key, $value)) {
                $result[$key] = filter_var($value[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $result['footer_text'] = mb_substr(trim((string) ($value['footer_text'] ?? '')), 0, 60);

        return $result;
    }
}
