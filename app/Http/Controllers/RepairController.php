<?php

namespace App\Http\Controllers;

use App\Models\Repair;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\RepairImage;
use Illuminate\Support\Facades\DB;

class RepairController extends Controller
{
    public function index(): Response
    {
        $repairs = Repair::query()

            ->latest()

            ->paginate(10);

        return Inertia::render(

            'Repairs/Index',

            [
                'repairs' => $repairs,
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Repairs/Create'
        );
    }

    // Lưu thông tin sửa chữa mới
    public function store(
            Request $request
        ): RedirectResponse {

            $request->validate([

                'customer_name' => [
                    'required',
                ],

                'device_name' => [
                    'required',
                ],

                'issue' => [
                    'required',
                ],

                'images.*' => [
                    'nullable',
                    'image',
                    'max:2048',
                ],
            ]);

            DB::beginTransaction();

            try {

                $repair = Repair::query()

                    ->create([

                        'code' =>

                            'SC-' .
                            now()->format('YmdHis'),

                        'customer_name' =>
                            $request->customer_name,

                        'customer_phone' =>
                            $request->customer_phone,

                        'device_name' =>
                            $request->device_name,

                        'imei' =>
                            $request->imei,

                        'issue' =>
                            $request->issue,

                        'accessories' =>
                            $request->accessories,

                        'repair_fee' => 0,

                        'status' => 'pending',
                    ]);

                /*
                |--------------------------------------------------------------------------
                | upload nhiều ảnh
                |--------------------------------------------------------------------------
                */

                if ($request->hasFile('images')) {

                    foreach (
                        $request->file('images')
                        as $image
                    ) {

                        $path = $image->store(
                            'repairs',
                            'public'
                        );

                        RepairImage::query()

                            ->create([

                                'repair_id' =>
                                    $repair->id,

                                'image' =>
                                    $path,
                            ]);
                    }
                }

                DB::commit();

                return redirect()

                    ->route('repairs.index');

            } catch (\Throwable $e) {

                DB::rollBack();

                throw $e;
            }
        }
}