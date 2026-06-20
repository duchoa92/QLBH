<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        $suppliers = Supplier::query()

            ->latest()

            ->get();

        return Inertia::render(
            'Suppliers/Index',
            [
                'suppliers' => $suppliers,
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Suppliers/Create'
        );
    }

    public function store(
        Request $request
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

        Supplier::create([

            'code' => 'NCC' .
                str_pad(
                    (string)
                    (
                        Supplier::count()
                        + 1
                    ),
                    5,
                    '0',
                    STR_PAD_LEFT
                ),

            'name' =>
                $validated['name'],

            'phone' =>
                $validated['phone']
                ?? null,

            'email' =>
                $validated['email']
                ?? null,

            'address' =>
                $validated['address']
                ?? null,
        ]);

        return redirect()
            ->route(
                'suppliers.index'
            );
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
}