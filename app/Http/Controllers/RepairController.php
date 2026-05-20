<?php

namespace App\Http\Controllers;

use App\Models\Repair;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\RepairImage;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\RepairRequest;


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

    // Hiển thị form tạo mới
    public function create(): Response
    {
        return Inertia::render(
            'Repairs/Create'
        );
    }

    // Lấy danh sách phụ kiện và lỗi phổ biến để gợi ý
    public function suggestions(): JsonResponse
    {
        $customers = Repair::query()

            ->select(
                'customer_name',
                'customer_phone'
            )

            ->whereNotNull('customer_name')

            ->groupBy(
                'customer_name',
                'customer_phone'
            )

            ->orderByRaw('MAX(created_at) DESC')

            ->limit(10)

            ->get();

         $issues = Repair::query()
            ->whereNotNull('issue')
            ->pluck('issue')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $accessories = Repair::query()
            ->whereNotNull('accessories')
            ->pluck('accessories')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'issues' => $issues,
            'accessories' => $accessories,
            'customers' => $customers,
        ]);
    }

    // Lưu thông tin sửa chữa mới
    public function store(
        RepairRequest $request
    ): RedirectResponse {

        $request->validate([

            'customer_name' => [
                'required',
            ],

            'device_name' => [
                'required',
                'string',
                'max:255',
                
            ],

            'issue' => [
                'required',
            ],

            'images.*' => [
                'nullable',
                'image',
                'max:2048',
            ],

            'accessories' => [
                'nullable',
                'array',
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

    // Tìm kiếm khách hàng để gợi ý khi nhập tên hoặc số điện thoại
    public function customerSearch(
        Request $request
    ): JsonResponse {

        $keyword = $request->keyword;

        if (! $keyword) {

            return response()->json([]);
        }

        $repairCustomers = Repair::query()

            ->select([ 'customer_name', 'customer_phone', ])

            ->where(function ($query) use ($keyword) {

                $query

                    ->where(
                        'customer_name',
                        'like',
                        '%' . $keyword . '%'
                    )

                    ->orWhere(
                        'customer_phone',
                        'like',
                        '%' . $keyword . '%'
                    );
            })

            ->get();

    
        $customers = $repairCustomers
            ->unique(function ($item) {

                return $item->customer_name .
                    $item->customer_phone;
            })

            ->take(10)

            ->values();

        return response()->json($customers);
    }

}