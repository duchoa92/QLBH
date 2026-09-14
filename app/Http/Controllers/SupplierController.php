<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function index(
        Request $request
    ): Response {

        $search = $request->string(
            'search'
        )->toString();

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

            ->latest()

            ->get();

        return Inertia::render(
            'Suppliers/Index',
            [
                'suppliers' => $suppliers,

                'filters' => [
                    'search' => $search,
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
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
        ]);

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
            ]);

        return response()->json(
            $suppliers
        );
    }
}