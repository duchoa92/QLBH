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
        ]);

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
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_bin' => $validated['bank_bin'] ?? null,
            'bank_account' => $validated['bank_account'] ?? null,
            'bank_account_name' => $validated['bank_account_name'] ?? null,
            'bank_payment_url' => $validated['bank_payment_url'] ?? null,
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
            ]);

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
            ]);

        return response()->json(
            $suppliers
        );
    }
}
