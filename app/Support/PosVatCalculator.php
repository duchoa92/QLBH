<?php

declare(strict_types=1);

namespace App\Support;

class PosVatCalculator
{
    public static function calculate(array $items, float $invoiceDiscount, float $rate): int
    {
        $lineBase = 0.0;
        $taxableBase = 0.0;

        foreach ($items as $item) {
            $lineTotal = (float) ($item['price'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
            $discountType = $item['discount_type'] ?? null;
            $discountValue = max(0, (float) ($item['discount_value'] ?? 0));
            $lineDiscount = $discountType === 'percent'
                ? $lineTotal * min(100, $discountValue) / 100
                : ($discountType === 'amount' ? min($lineTotal, $discountValue) : 0);
            $netLine = max(0, $lineTotal - $lineDiscount);
            $lineBase += $netLine;

            if (filter_var($item['apply_vat'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $taxableBase += $netLine;
            }
        }

        if ($lineBase <= 0 || $taxableBase <= 0 || $rate <= 0) {
            return 0;
        }

        $allocatedInvoiceDiscount = min(max(0, $invoiceDiscount), $invoiceDiscount * $taxableBase / $lineBase);
        $taxableAfterDiscount = max(0, $taxableBase - $allocatedInvoiceDiscount);

        return (int) round($taxableAfterDiscount * $rate / 100);
    }
}
