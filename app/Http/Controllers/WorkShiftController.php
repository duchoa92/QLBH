<?php

namespace App\Http\Controllers;

use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WorkShiftController extends Controller
{
    private const DENOMINATIONS = [500000, 200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000];

    public function index(): Response
    {
        return Inertia::render('WorkShifts/History', [
            'shifts' => $this->recentShifts(true),
        ]);
    }

    public function current(): JsonResponse
    {
        $shift = WorkShift::query()
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        return response()->json(['shift' => $shift ? $this->summary($shift) : null]);
    }

    public function history(): JsonResponse
    {
        return response()->json(['shifts' => $this->recentShifts()]);
    }

    private function recentShifts(bool $allUsers = false)
    {
        return WorkShift::query()
            ->when(! $allUsers, fn ($query) => $query->where('user_id', auth()->id()))
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subDays(30))
            ->with('user:id,name')
            ->orderByDesc('closed_at')
            ->get()
            ->map(fn (WorkShift $shift): array => $this->summary($shift) + [
                'closed_date' => $shift->closed_at?->toDateString(),
                'user_name' => $shift->user?->name,
            ]);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'denominations' => ['required', 'array'],
            'denominations.*' => ['required', 'integer', 'min:0', 'max:1000000'],
            'bank_balances' => ['nullable', 'array', 'max:10'],
            'bank_balances.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ]);
        $this->validateDenominations($data['denominations']);
        $openingAmount = $this->totalFromDenominations($data['denominations']);

        $shift = DB::transaction(function () use ($data, $openingAmount): WorkShift {
            WorkShift::query()
                ->where('user_id', auth()->id())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (WorkShift::query()->where('user_id', auth()->id())->whereNull('closed_at')->exists()) {
                abort(422, 'Bạn đang có ca làm chưa kết thúc.');
            }

            return WorkShift::create([
                'user_id' => auth()->id(),
                'opening_amount' => $openingAmount,
                'opening_denominations' => $data['denominations'],
                'opening_bank_balances' => $data['bank_balances'] ?? [],
                'opened_at' => now(),
            ]);
        });

        return response()->json(['shift' => $this->summary($shift)], 201);
    }

    public function close(Request $request): JsonResponse
    {
        $data = $request->validate([
            'denominations' => ['required', 'array'],
            'denominations.*' => ['required', 'integer', 'min:0', 'max:1000000'],
            'bank_balances' => ['nullable', 'array', 'max:10'],
            'bank_balances.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ]);
        $this->validateDenominations($data['denominations']);
        $closingAmount = $this->totalFromDenominations($data['denominations']);

        $shift = DB::transaction(function () use ($data, $closingAmount): WorkShift {
            $shift = WorkShift::query()
                ->where('user_id', auth()->id())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->firstOrFail();

            $cashCollected = (float) $shift->sales()
                ->where('status', '!=', 'cancelled')
                ->where('payment_method', 'cash')
                ->selectRaw('COALESCE(SUM(paid_amount - change_amount), 0) as total')
                ->value('total');
            $customerDebt = $this->customerDebtFor($shift);

            $shift->update([
                'cash_collected' => $cashCollected,
                'customer_debt_amount' => $customerDebt,
                'expected_cash' => (float) $shift->opening_amount + $cashCollected,
                'closing_amount' => $closingAmount,
                'closing_denominations' => $data['denominations'],
                'closing_bank_balances' => $data['bank_balances'] ?? [],
                'closed_at' => now(),
            ]);

            return $shift->refresh();
        });

        return response()->json(['summary' => $this->summary($shift)]);
    }

    private function summary(WorkShift $shift): array
    {
        $sales = $shift->sales()->where('status', '!=', 'cancelled');
        $cashCollected = $shift->cash_collected === null
            ? (float) (clone $sales)->where('payment_method', 'cash')->selectRaw('COALESCE(SUM(paid_amount - change_amount), 0) as total')->value('total')
            : (float) $shift->cash_collected;
        $expectedCash = $shift->expected_cash === null
            ? (float) $shift->opening_amount + $cashCollected
            : (float) $shift->expected_cash;
        $customerDebt = $shift->customer_debt_amount === null
            ? $this->customerDebtFor($shift)
            : (float) $shift->customer_debt_amount;

        return [
            'id' => $shift->id,
            'user_id' => $shift->user_id,
            'opening_amount' => (float) $shift->opening_amount,
            'opening_denominations' => $shift->opening_denominations,
            'opening_bank_balances' => $shift->opening_bank_balances,
            'cash_collected' => $cashCollected,
            'customer_debt_amount' => $customerDebt,
            'expected_cash' => $expectedCash,
            'closing_amount' => $shift->closing_amount === null ? null : (float) $shift->closing_amount,
            'closing_denominations' => $shift->closing_denominations,
            'closing_bank_balances' => $shift->closing_bank_balances,
            'difference' => $shift->closing_amount === null ? null : (float) $shift->closing_amount - $expectedCash,
            'opened_at' => $shift->opened_at?->format('d/m/Y H:i'),
            'closed_at' => $shift->closed_at?->format('d/m/Y H:i'),
            'sale_count' => (int) (clone $sales)->count(),
        ];
    }

    private function validateDenominations(array $denominations): void
    {
        $invalid = array_diff(array_map('intval', array_keys($denominations)), self::DENOMINATIONS);
        if ($invalid !== []) {
            abort(422, 'Mệnh giá tiền không hợp lệ.');
        }
    }

    private function totalFromDenominations(array $denominations): int
    {
        $total = 0;

        foreach ($denominations as $value => $count) {
            $total += (int) $value * (int) $count;
        }

        return $total;
    }

    private function customerDebtFor(WorkShift $shift): float
    {
        return (float) $shift->sales()
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN grand_total > paid_amount THEN grand_total - paid_amount ELSE 0 END), 0) as total')
            ->value('total');
    }
}

