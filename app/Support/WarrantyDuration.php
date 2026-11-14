<?php

namespace App\Support;

/**
 * Thời hạn bảo hành: đổi qua lại giữa (giá trị + đơn vị) và số ngày.
 * Quy ước: 1 tuần = 7 ngày, 1 tháng = 30 ngày, 1 năm = 365 ngày (khớp resources/js/utils/warranty.js).
 * Đơn vị chấp nhận cả dạng số ít (month) lẫn số nhiều (months, dùng ở phiếu nhập kho).
 */
final class WarrantyDuration
{
    private const UNIT_DAYS = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];

    public const MAX_DAYS = 3650;

    /** 'months' | 'month' -> 'month'; giá trị lạ -> null. */
    public static function normalizeUnit(mixed $unit): ?string
    {
        $key = rtrim(strtolower(trim((string) $unit)), 's');

        return isset(self::UNIT_DAYS[$key]) ? $key : null;
    }

    public static function toDays(mixed $value, mixed $unit): int
    {
        $normalized = self::normalizeUnit($unit) ?? 'month';

        return min(self::MAX_DAYS, max(0, (int) round(((float) $value) * self::UNIT_DAYS[$normalized])));
    }

    /**
     * Số ngày -> ['value' => n, 'unit' => 'months'] dùng đơn vị lớn nhất chia hết. Không có bảo hành -> null.
     *
     * @return array{value: int, unit: string}|null
     */
    public static function fromDays(int $days, bool $plural = true): ?array
    {
        if ($days <= 0) {
            return null;
        }

        foreach (['year', 'month', 'week'] as $unit) {
            if ($days % self::UNIT_DAYS[$unit] === 0) {
                return ['value' => intdiv($days, self::UNIT_DAYS[$unit]), 'unit' => $plural ? $unit.'s' : $unit];
            }
        }

        return ['value' => $days, 'unit' => $plural ? 'days' : 'day'];
    }

    /**
     * Bảo hành khách đã ghi lúc nhập hàng (extra_info của dòng nhập / IMEI) quy ra ngày.
     * Không có thông tin lúc nhập -> null để nơi gọi dùng giá trị dự phòng.
     */
    public static function daysFromImportExtra(mixed $extraInfo): ?int
    {
        if (! is_array($extraInfo)) {
            return null;
        }

        $value = (float) ($extraInfo['customer_warranty_duration_value'] ?? 0);
        if ($value <= 0) {
            return null;
        }

        $days = self::toDays($value, $extraInfo['customer_warranty_duration_unit'] ?? 'months');

        return $days > 0 ? $days : null;
    }
}
