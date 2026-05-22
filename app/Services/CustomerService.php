<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\CustomerDebt;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerService
{
    /*
    |--------------------------------------------------
    | UPDATE CUSTOMER SUMMARY (CACHE FIELDS)
    |--------------------------------------------------
    */
    public function recalculate(Customer $customer): void
    {
        $customer->point_balance = $this->calculatePoints($customer);
        $customer->debt_balance  = $this->calculateDebt($customer);
        $customer->total_spent   = $this->calculateTotalSpent($customer);
        $customer->total_orders  = $this->calculateTotalOrders($customer);
        $customer->last_order_at = $this->getLastOrderAt($customer);

        $customer->save();
    }

    /*
    |--------------------------------------------------
    | POINTS
    |--------------------------------------------------
    */
    public function addPoints(
        Customer $customer,
        int $points,
        string $sourceType = null,
        int $sourceId = null,
        string $note = null
    ): CustomerPoint {
        return DB::transaction(function () use ($customer, $points, $sourceType, $sourceId, $note) {

            $record = CustomerPoint::create([
                'customer_id' => $customer->id,
                'type' => 'earn',
                'points' => $points,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
            ]);

            $customer->increment('point_balance', $points);

            return $record;
        });
    }

    public function redeemPoints(
        Customer $customer,
        int $points,
        string $sourceType = null,
        int $sourceId = null,
        string $note = null
    ): CustomerPoint {
        return DB::transaction(function () use ($customer, $points, $sourceType, $sourceId, $note) {

            if ($customer->point_balance < $points) {
                throw new \Exception('Không đủ điểm');
            }

            $record = CustomerPoint::create([
                'customer_id' => $customer->id,
                'type' => 'redeem',
                'points' => $points,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
            ]);

            $customer->decrement('point_balance', $points);

            return $record;
        });
    }

    /*
    |--------------------------------------------------
    | DEBT (CÔNG NỢ)
    |--------------------------------------------------
    */
    public function addDebt(
        Customer $customer,
        float $amount,
        string $sourceType = null,
        int $sourceId = null,
        string $note = null
    ): CustomerDebt {
        return DB::transaction(function () use ($customer, $amount, $sourceType, $sourceId, $note) {

            $record = CustomerDebt::create([
                'customer_id' => $customer->id,
                'type' => 'increase',
                'amount' => $amount,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
            ]);

            $customer->increment('debt_balance', $amount);

            return $record;
        });
    }

    public function reduceDebt(
        Customer $customer,
        float $amount,
        string $sourceType = null,
        int $sourceId = null,
        string $note = null
    ): CustomerDebt {
        return DB::transaction(function () use ($customer, $amount, $sourceType, $sourceId, $note) {

            $record = CustomerDebt::create([
                'customer_id' => $customer->id,
                'type' => 'decrease',
                'amount' => $amount,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
            ]);

            $customer->decrement('debt_balance', $amount);

            return $record;
        });
    }

    /*
    |--------------------------------------------------
    | STATISTICS
    |--------------------------------------------------
    */

    private function calculatePoints(Customer $customer): int
    {
        return CustomerPoint::where('customer_id', $customer->id)
            ->selectRaw("
                SUM(
                    CASE 
                        WHEN type = 'earn' THEN points
                        WHEN type = 'redeem' THEN -points
                        ELSE 0
                    END
                ) as total
            ")
            ->value('total') ?? 0;
    }

    private function calculateDebt(Customer $customer): float
    {
        return CustomerDebt::where('customer_id', $customer->id)
            ->selectRaw("
                SUM(
                    CASE 
                        WHEN type = 'increase' THEN amount
                        WHEN type = 'decrease' THEN -amount
                        ELSE 0
                    END
                ) as total
            ")
            ->value('total') ?? 0;
    }

    private function calculateTotalSpent(Customer $customer): float
    {
        // sẽ nối với sales sau
        return 0;
    }

    private function calculateTotalOrders(Customer $customer): int
    {
        // sẽ nối sales + repair sau
        return 0;
    }

    private function getLastOrderAt(Customer $customer): ?Carbon
    {
        // sẽ nối sales + repair sau
        return null;
    }
}