<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RepairRequest;
use App\Models\Repair;
use App\Models\RepairImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\RepairTimeline;

class RepairController extends Controller
{
    /**
     * Danh sách phiếu sửa.
     */
    public function index(
        Request $request
    ): Response {

        $search = trim(
            (string) $request->input(
                'search'
            )
        );

        $status = trim(
            (string) $request->input(
                'status'
            )
        );

        $repairs = Repair::query()

            ->when(

                $search !== '',

                function ($query) use ($search): void {

                    $query->where(

                        function ($q) use ($search): void {

                            $q

                                ->where(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'customer_name',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'customer_phone',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'imei',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'device_name',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )

            ->when(

                $status !== '',

                function ($query) use ($status): void {

                    $query->where(
                        'status',
                        $status
                    );
                }
            )

            ->latest()

            ->paginate(20)

            ->through(fn ($repair) => [

                'id' => $repair->id,

                'code' => $repair->code,

                'customer_name' =>
                    $repair->customer_name,

                'customer_phone' =>
                    $repair->customer_phone,

                'device_name' =>
                    $repair->device_name,

                'imei' =>
                    $repair->imei,

                'status' =>
                    $repair->status,

                'created_at' =>
                    $repair->created_at?->format(
                        'd/m/Y H:i'
                    ),
            ])

            ->withQueryString();

        return inertia(

            'Repairs/Index',

            [

                'repairs' => $repairs,

                'filters' => [

                    'search' => $search,

                    'status' => $status,
                ],
            ]
        );
    }

    /**
     * Form tạo phiếu sửa.
     */
    public function create(): Response
    {
        return Inertia::render(
            'Repairs/Create'
        );
    }

    /**
     * Gợi ý dữ liệu.
     */
    public function suggestions(): JsonResponse
    {
        $customers = Repair::query()
            ->select([
                'customer_name',
                'customer_phone',
            ])
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

    /**
     * Lưu phiếu sửa.
     */
    public function store(
        RepairRequest $request
    ): RedirectResponse {

        DB::beginTransaction();

        try {

            $repair = Repair::query()
                ->create([

                    'code' => 'SC-' . now()->format('YmdHis'),

                    'customer_name' =>
                        $request->customer_name,

                    'customer_phone' =>
                        $request->customer_phone,

                    'contact_phone' =>
                        $request->contact_phone,

                    'device_name' =>
                        $request->device_name,

                    'imei' =>
                        $request->imei,

                    'screen_password' =>
                        $request->screen_password,

                    'screen_pattern' =>
                        $request->screen_pattern,

                    'account_type' =>
                        $request->account_type,

                    'account_email' =>
                        $request->account_email,

                    'account_password' =>
                        $request->account_password,

                    'issue' =>
                        $request->issue,

                    'accessories' =>
                        $request->accessories,

                    'note' =>
                        $request->note,

                    'status' => 'pending',

                    'received_at' => now(),
                ]);



                        /*
            |--------------------------------------------------------------------------
            | Tạo timeline
            |--------------------------------------------------------------------------
            */

            RepairTimeline::create([

                'repair_id' =>
                    $repair->id,

                'user_id' =>
                    auth()->id(),

                'status' => 'pending',

                'title' =>
                    'Đã tiếp nhận máy',

                'description' =>
                    'Tạo phiếu sửa chữa mới',
            ]);




            /*
            |--------------------------------------------------------------------------
            | Upload ảnh
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

                            'image_path' =>
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

    /**
     * Tìm khách hàng.
     */
    public function customerSearch(
        Request $request
    ): JsonResponse {

        $keyword = $request->keyword;

        if (! $keyword) {

            return response()->json([]);
        }

        $repairCustomers = Repair::query()

            ->select([
                'customer_name',
                'customer_phone',
            ])

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

            ->latest()
            ->limit(10)
            ->get();

        $customers = $repairCustomers
            ->unique(function ($item) {

                return
                    $item->customer_name .
                    $item->customer_phone;
            })

            ->values();

        return response()->json($customers);
    }

        /**
     * hiện chi tiết sửa chữa.
     */
    public function show(
        Repair $repair
    ): Response {

        $repair->load([
            'images',
            'technician',
            'timelines.user',
        ]);

        return inertia(

            'Repairs/Show',

            [

                'repair' => $repair,
            ]
        );
    }

        /**
     * Cập nhật trạng thái sửa chữa.
     */
    public function updateStatus(
        Request $request,
        Repair $repair
    ): RedirectResponse {

        $request->validate([

            'status' => [
                'required',
                'string',
            ],
        ]);

        $status =
            $request->string('status');

        $repair->update([

            'status' => $status,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Tiêu đề timeline
        |--------------------------------------------------------------------------
        */

        $titles = [

            'pending' =>
                'Đã tiếp nhận máy',

            'checking' =>
                'Đang kiểm tra máy',

            'waiting_parts' =>
                'Đang chờ linh kiện',

            'repairing' =>
                'Đang sửa chữa',

            'done' =>
                'Đã sửa xong',

            'returned' =>
                'Đã trả máy cho khách',

            'cancelled' =>
                'Phiếu sửa đã hủy',
        ];

        RepairTimeline::create([

            'repair_id' =>
                $repair->id,

            'user_id' =>
                auth()->id(),

            'status' => $status,

            'title' =>
                $titles[$status]
                ?? 'Cập nhật trạng thái',

            'description' =>
                null,
        ]);

        return back()->with(

            'success',

            'Cập nhật trạng thái thành công'
        );
    }



        /**
     * In hóa đơn sửa chữa.
     */
    public function print(
        Repair $repair
    ) {

        $repair->load([
            'images',
        ]);

        return view(

            'prints.repair',

            [

                'repair' => $repair,
            ]
        );
    }

        /**
     * sửa phiếu sửa chữa.
     */
    public function edit(
        Repair $repair
    ): Response {

        $repair->load([
            'images',
        ]);

        return inertia(

            'Repairs/Edit',

            [

                'repair' => $repair,
            ]
        );
    }

        /**
     * Cập nhật phiếu sửa chữa.
     */
    public function update(
        Request $request,
        Repair $repair
    ): RedirectResponse {

        $validated = $request->validate([

            'device_name' => [
                'required',
                'string',
                'max:255',
            ],

            'imei' => [
                'nullable',
                'string',
                'max:255',
            ],

            'issue' => [
                'nullable',
                'array',
            ],

            'repair_request' => [
                'nullable',
                'string',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            'estimated_cost' => [
                'nullable',
            ],

            'final_cost' => [
                'nullable',
            ],

            'status' => [
                'required',
                'string',
            ],

            'images.*' => [
                'nullable',
                'image',
                'max:5120',
            ],
        ]);

        DB::beginTransaction();

        try {

            $repair->update([

                'device_name' =>
                    $validated['device_name'],

                'imei' =>
                    $validated['imei'] ?? null,

                'issue' =>
                    $validated['issue'] ?? [],

                'repair_request' =>
                    $validated['repair_request']
                    ?? null,

                'note' =>
                    $validated['note']
                    ?? null,

                'estimated_cost' =>
                    $validated['estimated_cost']
                    ?? null,

                'final_cost' =>
                    $validated['final_cost']
                    ?? null,

                'status' =>
                    $validated['status'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Upload ảnh mới
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

                    RepairImage::create([

                        'repair_id' =>
                            $repair->id,

                        'image_path' =>
                            $path,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Timeline
            |--------------------------------------------------------------------------
            */

            RepairTimeline::create([

                'repair_id' =>
                    $repair->id,

                'user_id' =>
                    auth()->id(),

                'status' =>
                    $validated['status'],

                'title' =>
                    'Cập nhật phiếu sửa',

                'description' =>
                    'Kỹ thuật viên cập nhật thông tin sửa chữa',
            ]);

            DB::commit();

            return redirect()->route(

                'repairs.show',

                $repair
            );

        } catch (\Throwable $e) {

            DB::rollBack();

            throw $e;
        }
    }
}