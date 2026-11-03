<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(
        Request $request
    ): Response {

        $search = $request->string(
            'search'
        )->toString();
        $perPage = in_array((int) $request->query('per_page', 20), [10, 20, 50, 100], true)
            ? (int) $request->query('per_page', 20) : 20;
        $sortBy = in_array($request->query('sort_by'), ['code', 'name', 'phone', 'debt_balance'], true)
            ? $request->query('sort_by') : 'created_at';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';

        $suppliers = Supplier::query()

            ->when(
                $search,
                function ($query) use (
                    $search
                ) {

                    $query->where(
                        function ($subQuery)
                        use (
                            $search
                        ) {

                            $subQuery

                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )

            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Suppliers/Index',
            [
                'suppliers' => $suppliers,

                'filters' => [
                    'search' => $search,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'per_page' => $perPage,
                ],
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Suppliers/Create'
        );
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_bin' => ['nullable', 'digits:6'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_payment_url' => ['nullable', 'string', 'max:2048'],
            'bank_accounts' => ['sometimes', 'array', 'max:10'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.bank_bin' => ['nullable', 'digits:6'],
            'bank_accounts.*.bank_account' => ['nullable', 'string', 'max:50'],
            'bank_accounts.*.bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.bank_payment_url' => ['nullable', 'string', 'max:2048'],
            'bank_accounts.*.is_default' => ['sometimes', 'boolean'],
        ]);

        $bankAccounts = $this->normalizeBankAccounts($validated);
        $defaultBankAccount = collect($bankAccounts)->firstWhere('is_default') ?? ($bankAccounts[0] ?? []);

        $supplier = Supplier::create([
            'code' => 'NCC' . str_pad(
                (string)(Supplier::count() + 1),
                5,
                '0',
                STR_PAD_LEFT
            ),
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'bank_accounts' => $bankAccounts,
            'bank_name' => $defaultBankAccount['bank_name'] ?? null,
            'bank_bin' => $defaultBankAccount['bank_bin'] ?? null,
            'bank_account' => $defaultBankAccount['bank_account'] ?? null,
            'bank_account_name' => $defaultBankAccount['bank_account_name'] ?? null,
            'bank_payment_url' => $defaultBankAccount['bank_payment_url'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $supplier], 201);
        }

        return back()->with([
            'success' => 'Tạo nhà cung cấp thành công',
            'newSupplier' => $supplier
        ]);
    }

    public function edit(
        Supplier $supplier
    ): Response {

        return Inertia::render(
            'Suppliers/Edit',
            [
                'supplier' =>
                    $supplier,
            ]
        );
    }

    public function update(
        Request $request,
        Supplier $supplier
    ): RedirectResponse {

        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                ],

                'address' => [
                    'nullable',
                    'string',
                ],

                'bank_name' => ['nullable', 'string', 'max:150'],
                'bank_bin' => ['nullable', 'digits:6'],
                'bank_account' => ['nullable', 'string', 'max:50'],
                'bank_account_name' => ['nullable', 'string', 'max:150'],
                'bank_payment_url' => ['nullable', 'string', 'max:2048'],
                'bank_accounts' => ['sometimes', 'array', 'max:10'],
                'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:150'],
                'bank_accounts.*.bank_bin' => ['nullable', 'digits:6'],
                'bank_accounts.*.bank_account' => ['nullable', 'string', 'max:50'],
                'bank_accounts.*.bank_account_name' => ['nullable', 'string', 'max:150'],
                'bank_accounts.*.bank_payment_url' => ['nullable', 'string', 'max:2048'],
                'bank_accounts.*.is_default' => ['sometimes', 'boolean'],
            ]);

        $bankAccounts = $this->normalizeBankAccounts($validated);
        $defaultBankAccount = collect($bankAccounts)->firstWhere('is_default') ?? ($bankAccounts[0] ?? []);
        $validated['bank_accounts'] = $bankAccounts;
        foreach (['bank_name', 'bank_bin', 'bank_account', 'bank_account_name', 'bank_payment_url'] as $field) {
            $validated[$field] = $defaultBankAccount[$field] ?? null;
        }

        $supplier->update(
            $validated
        );

        return redirect()
            ->route(
                'suppliers.index'
            );
    }

    public function destroy(
        Supplier $supplier
    ): RedirectResponse {

        $supplier->delete();

        return back();
    }

    private function normalizeBankAccounts(array $data): array
    {
        $accounts = $data['bank_accounts'] ?? [[
            'bank_name' => $data['bank_name'] ?? null,
            'bank_bin' => $data['bank_bin'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'bank_payment_url' => $data['bank_payment_url'] ?? null,
            'is_default' => true,
        ]];

        $accounts = collect($accounts)->filter(fn ($account) => filled($account['bank_account'] ?? null))
            ->map(fn ($account) => [
                'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                'bank_bin' => trim((string) ($account['bank_bin'] ?? '')),
                'bank_account' => trim((string) $account['bank_account']),
                'bank_account_name' => trim((string) ($account['bank_account_name'] ?? '')),
                'bank_payment_url' => trim((string) ($account['bank_payment_url'] ?? '')),
                'is_default' => (bool) ($account['is_default'] ?? false),
            ])->values()->all();

        if ($accounts && ! collect($accounts)->contains('is_default', true)) {
            $accounts[0]['is_default'] = true;
        }

        $defaultChosen = false;
        foreach ($accounts as &$account) {
            $account['is_default'] = $account['is_default'] && ! $defaultChosen;
            $defaultChosen = $defaultChosen || $account['is_default'];
        }
        unset($account);

        return $accounts;
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request
            ->string('search')
            ->trim()
            ->toString();

        if ($search === '') {
            return response()->json([]);
        }

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->where(function ($query) use ($search) {

                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");

            })
            ->orderBy('name')
            ->limit(20)
            ->get([
                'id',
                'code',
                'name',
                'phone',
                'email',
                'debt_balance',
                'bank_name',
                'bank_bin',
                'bank_account',
                'bank_account_name',
                'bank_payment_url',
                'bank_accounts',
            ]);

        return response()->json(
            $suppliers
        );
    }
}
