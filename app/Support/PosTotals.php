<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nguồn tính tiền duy nhất cho POS (thanh toán thường, QR ngân hàng, webhook).
 * Trước đây logic này bị lặp ở PosController và PosCheckoutService nên dễ lệch số.
 */
class PosTotals
{
    public static function lineQuantity(array $item): int
    {
        return max(1, (int) ($item['quantity'] ?? 1));
    }

    public static function lineTotal(array $item): float
    {
        return (float) ($item['price'] ?? 0) * self::lineQuantity($item);
    }

    /** Giảm giá của một dòng, luôn nằm trong khoảng 0..thành tiền dòng. */
    public static function lineDiscount(array $item): float
    {
        $lineTotal = self::lineTotal($item);
        $value = max(0, (float) ($item['discount_value'] ?? 0));

        return match ($item['discount_type'] ?? null) {
            'percent' => $lineTotal * min(100, $value) / 100,
            'amount' => min($lineTotal, $value),
            default => 0.0,
        };
    }

    /**
     * @return array{subtotal: float, discount: float, invoice_discount: float, tax: int, extra_fee: float, grand_total: float}
     */
    public static function calculate(
        array $items,
        float $invoiceDiscount = 0,
        string $invoiceDiscountType = 'amount',
        float $extraFee = 0,
        float $vatRate = 0,
    ): array {
        $subtotal = 0.0;
        $discount = 0.0;

        foreach ($items as $item) {
            $subtotal += self::lineTotal($item);
            $discount += self::lineDiscount($item);
        }

        $base = max(0, $subtotal - $discount);
        $invoiceDiscount = $invoiceDiscountType === 'percent'
            ? $base * min(100, max(0, $invoiceDiscount)) / 100
            : min($base, max(0, $invoiceDiscount));
        $extraFee = max(0, $extraFee);
        $tax = PosVatCalculator::calculate($items, $invoiceDiscount, $vatRate);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'invoice_discount' => $invoiceDiscount,
            'tax' => $tax,
            'extra_fee' => $extraFee,
            'grand_total' => max(0, $subtotal - $discount - $invoiceDiscount + $tax + $extraFee),
        ];
    }
}
