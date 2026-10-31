<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\Sale;

class CustomerPointService
{
    public function syncSale(Sale $sale): void
    {
        $sale->refresh();
        $threshold = max(1, (int) setting('customer_points_amount_per_point', 10000));
        $isEnabled = filter_var(setting('customer_points_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $basis = setting('customer_points_basis', 'revenue');
        $debtPointsEnabled = filter_var(setting('customer_points_debt_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $debtMode = setting('customer_points_debt_mode', 'same');
        $debtPercent = min(100, max(1, (int) setting('customer_points_debt_percent', 50)));
        $recognizedPaid = min(max(0, (float) $sale->grand_total), max(0, (float) $sale->paid_amount - (float) $sale->change_amount));
        $hasDebt = $recognizedPaid + 0.009 < (float) $sale->grand_total
            || $sale->customerDebtEntries()->where('type', 'increase')->exists();
        $debtMultiplier = $hasDebt && !$debtPointsEnabled
            ? 0
            : ($hasDebt
            ? match ($debtMode) {
                'none' => 0,
                'half' => 0.5,
                'custom' => $debtPercent / 100,
                default => 1,
            }
            : 1);
        $eligibleAmount = max(0, (float) $sale->grand_total);
        if ($basis === 'profit') {
            $costTotal = $sale->items()
                ->with(['product:id,cost_price', 'variant:id,cost_price', 'productImei:id,cost_price', 'gifts.product:id,cost_price', 'gifts.variant:id,cost_price', 'gifts.productImei:id,cost_price'])
                ->get()
                ->sum(function ($item): float {
                    $unitCost = $item->unit_cost
                        ?? $item->productImei?->cost_price
                        ?? $item->variant?->cost_price
                        ?? $item->product?->cost_price
                        ?? 0;

                    $lineCost = max(0, (float) $unitCost) * max(0, (int) ($item->quantity ?: 1));
                    foreach ($item->gifts as $gift) {
                        $giftUnitCost = $gift->unit_cost
                            ?? $gift->productImei?->cost_price
                            ?? $gift->variant?->cost_price
                            ?? $gift->product?->cost_price
                            ?? 0;
                        $lineCost += max(0, (float) $giftUnitCost) * max(0, (int) ($gift->quantity ?: 1));
                    }

                    return $lineCost;
                });
            $eligibleAmount = max(0, $eligibleAmount - $costTotal);
        }
        $targetCustomerId = $isEnabled && $sale->status === 'completed' ? $sale->customer_id : null;
        $targetPoints = $targetCustomerId ? (int) floor(($eligibleAmount * $debtMultiplier) / $threshold) : 0;

        $entries = CustomerPoint::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->lockForUpdate()
            ->get()
            ->groupBy('customer_id');
        $customerIds = $entries->keys()->map(fn ($id) => (int) $id);
        if ($targetCustomerId) $customerIds->push((int) $targetCustomerId);

        foreach ($customerIds->unique() as $customerId) {
            $history = $entries->get($customerId, collect());
            $currentPoints = (int) $history->sum(fn (CustomerPoint $entry) => $entry->type === 'earn' ? $entry->points : -$entry->points);
            $desiredPoints = (int) $customerId === (int) $targetCustomerId ? $targetPoints : 0;
            $delta = $desiredPoints - $currentPoints;
            if ($delta === 0) continue;

            $customer = Customer::withTrashed()->lockForUpdate()->find($customerId);
            if (!$customer) continue;

            $customer->increment('point_balance', $delta);
            CustomerPoint::query()->create([
                'customer_id' => $customerId,
                'type' => $delta > 0 ? 'earn' : 'redeem',
                'points' => abs($delta),
                'source_type' => Sale::class,
                'source_id' => $sale->id,
                'note' => $delta > 0
                    ? 'Tích điểm hóa đơn ' . $sale->code
                    : 'Điều chỉnh/hoàn tác điểm hóa đơn ' . $sale->code,
            ]);
        }
    }
}
