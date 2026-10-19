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

        return response()->json([
            'shift' => $shift ? $this->summary($shift) : null,
            'prompt_dismissed' => (bool) request()->session()->get('work_shift_auto_open_dismissed', false),
        ]);
    }

    public function dismissPrompt(Request $request): JsonResponse
    {
        $request->session()->put('work_shift_auto_open_dismissed', true);

        return response()->json(['dismissed' => true]);
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
            'cash_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'bank_balances' => ['nullable', 'array', 'max:10'],
            'bank_balances.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ]);
        $this->validateDenominations($data['denominations']);
        $openingAmount = isset($data['cash_amount'])
            ? (float) $data['cash_amount']
            : $this->totalFromDenominations($data['denominations']);

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

        $request->session()->put('work_shift_auto_open_dismissed', true);

        return response()->json(['shift' => $this->summary($shift)], 201);
    }

    public function close(Request $request): JsonResponse
    {
        $data = $request->validate([
            'denominations' => ['required', 'array'],
            'denominations.*' => ['required', 'integer', 'min:0', 'max:1000000'],
            'cash_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'bank_balances' => ['nullable', 'array', 'max:10'],
            'bank_balances.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ]);
        $this->validateDenominations($data['denominations']);
        $closingAmount = isset($data['cash_amount'])
            ? (float) $data['cash_amount']
            : $this->totalFromDenominations($data['denominations']);

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

        $request->session()->put('work_shift_auto_open_dismissed', true);

        return response()->json(['summary' => $this->summary($shift)]);
    }

    private function summary(WorkShift $shift): array
    {
        $sales = $shift->sales()->where('status', '!=', 'cancelled');
        $bankCollection = $this->bankCollectionFor($shift);
        $expectedBankBalances = $shift->opening_bank_balances ?? [];
        foreach ($bankCollection['by_account'] as $key => $amount) {
            $expectedBankBalances[$key] = (float) ($expectedBankBalances[$key] ?? 0) + $amount;
        }
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
            'bank_collected' => $bankCollection['by_account'],
            'expected_bank_balances' => $expectedBankBalances,
            'bank_sales' => $bankCollection['sales'],
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

    private function bankCollectionFor(WorkShift $shift): array
    {
        $accounts = $this->configuredBankAccounts();
        $byAccount = [];
        foreach ($accounts as $account) {
            $byAccount[$account['key']] = 0.0;
        }

        $bankSales = $shift->sales()
            ->where('status', '!=', 'cancelled')
            ->where('payment_method', 'bank')
            ->orderBy('created_at')
            ->get(['id', 'code', 'bank_account', 'paid_amount', 'created_at']);

        $sales = $bankSales->map(function ($sale) use ($accounts, &$byAccount): array {
            $account = collect($accounts)->first(fn (array $item): bool => (string) ($item['bank_account'] ?? '') === (string) ($sale->bank_account ?? ''));
            $key = $account['key'] ?? ($sale->bank_account ?: 'unassigned');
            $amount = (float) $sale->paid_amount;
            $byAccount[$key] = (float) ($byAccount[$key] ?? 0) + $amount;

            return [
                'id' => $sale->id,
                'code' => $sale->code,
                'bank_account' => $sale->bank_account,
                'bank_name' => $account['bank_name'] ?? null,
                'amount' => $amount,
                'created_at' => $sale->created_at?->format('d/m/Y H:i'),
            ];
        })->values()->all();

        return ['by_account' => $byAccount, 'sales' => $sales];
    }

    private function configuredBankAccounts(): array
    {
        $accounts = json_decode((string) setting('bank_accounts', '[]'), true) ?: [];
        $accounts = array_values(array_filter($accounts, fn ($account) => filled($account['bank_account'] ?? null)));

        if ($accounts === [] && filled(setting('bank_account', ''))) {
            $accounts[] = [
                'id' => 'default',
                'bank_name' => 'Ngân hàng',
                'bank_account' => setting('bank_account', ''),
            ];
        }

        return array_map(function (array $account): array {
            $account['key'] = (string) (($account['id'] ?? null) ?: $account['bank_account']);

            return $account;
        }, $accounts);
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

