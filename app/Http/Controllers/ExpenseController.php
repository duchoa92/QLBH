<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\RecurringExpensePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    private const CATEGORIES = ['utilities', 'internet', 'rent', 'tax', 'salary', 'supplies', 'other'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'category' => ['nullable', 'in:utilities,internet,rent,tax,salary,supplies,other'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $expenses = Expense::query()
            ->with('user:id,name')
            ->whereBetween('expense_date', [$month . '-01', now()->parse($month . '-01')->endOfMonth()->toDateString()])
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('description', 'like', "%{$search}%")->orWhere('recipient', 'like', "%{$search}%")))
            ->orderByDesc('expense_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'filters' => ['month' => $month, 'category' => $filters['category'] ?? '', 'search' => $filters['search'] ?? ''],
            'summary' => ['total' => (float) Expense::whereBetween('expense_date', [$month . '-01', now()->parse($month . '-01')->endOfMonth()->toDateString()])->sum('amount'), 'count' => (int) Expense::whereBetween('expense_date', [$month . '-01', now()->parse($month . '-01')->endOfMonth()->toDateString()])->count()],
            'categories' => self::CATEGORIES,
            'canManage' => $request->user()->can('expenses.manage'),
            'recurringExpenses' => RecurringExpense::query()->with(['payments' => fn ($query) => $query->where('period', $month)])
                ->orderBy('due_day')->orderBy('description')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        Expense::create($data);
        return back()->with('success', 'Đã ghi nhận khoản chi.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->validated($request));
        return back()->with('success', 'Đã cập nhật khoản chi.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();
        return back()->with('success', 'Đã xóa khoản chi.');
    }

    public function storeRecurring(Request $request): RedirectResponse
    {
        $data = $this->validatedRecurring($request);
        $data['user_id'] = $request->user()->id;
        RecurringExpense::create($data);
        return back()->with('success', 'Đã tạo lịch nhắc chi phí cố định.');
    }

    public function updateRecurring(Request $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $recurringExpense->update($this->validatedRecurring($request));
        return back()->with('success', 'Đã cập nhật lịch chi phí cố định.');
    }

    public function destroyRecurring(RecurringExpense $recurringExpense): RedirectResponse
    {
        $recurringExpense->delete();
        return back()->with('success', 'Đã xóa lịch chi phí cố định.');
    }

    public function markRecurringPaid(Request $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'paid_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'payment_method' => ['required', 'in:cash,bank,card'],
        ]);

        DB::transaction(function () use ($request, $recurringExpense, $data): void {
            $expense = Expense::create([
                'category' => $recurringExpense->category,
                'description' => $recurringExpense->description,
                'amount' => $data['amount'],
                'expense_date' => $data['paid_date'],
                'payment_method' => $data['payment_method'],
                'recipient' => $recurringExpense->recipient,
                'note' => $recurringExpense->note,
                'user_id' => $request->user()->id,
            ]);
            RecurringExpensePayment::create([
                'recurring_expense_id' => $recurringExpense->id,
                'period' => $data['period'],
                'paid_date' => $data['paid_date'],
                'amount' => $data['amount'],
                'expense_id' => $expense->id,
                'user_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Đã ghi nhận đã thanh toán và thêm vào sổ chi phí.');
    }

    public static function dashboardReminders(): array
    {
        if (! auth()->user()?->can('expenses.view')) return [];
        $today = now()->startOfDay();
        $rows = [];
        foreach (RecurringExpense::query()->where('is_active', true)->with('payments')->get() as $schedule) {
            foreach ([$today->copy(), $today->copy()->addMonthNoOverflow()->startOfMonth()] as $monthStart) {
                $period = $monthStart->format('Y-m');
                if ($schedule->payments->contains('period', $period)) continue;
                $due = $monthStart->copy()->day(min((int) $schedule->due_day, $monthStart->daysInMonth));
                $daysUntil = (int) $today->diffInDays($due, false);
                if ($daysUntil <= (int) $schedule->remind_days && ($daysUntil >= 0 || $period === $today->format('Y-m'))) {
                    $rows[] = ['id' => $schedule->id, 'description' => $schedule->description, 'category' => $schedule->category, 'amount' => (float) $schedule->amount, 'due_date' => $due->toDateString(), 'period' => $period, 'days_until' => $daysUntil];
                }
            }
        }
        usort($rows, fn ($a, $b) => $a['due_date'] <=> $b['due_date']);
        return $rows;
    }

    private function validatedRecurring(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'in:' . implode(',', self::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'remind_days' => ['required', 'integer', 'between:0,30'],
            'payment_method' => ['required', 'in:cash,bank,card'],
            'recipient' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'in:' . implode(',', self::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank,card'],
            'recipient' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
