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
use Illuminate\Validation\Rule;
use Inertia\Response;
use App\Models\RepairTimeline;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RepairPart;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

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

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $sortBy = in_array($request->input('sort_by'), ['code', 'device_name', 'status', 'created_at'], true)
            ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $repairs = Repair::query()

            ->with(['customer', 'images', 'technician', 'timelines.user', 'timelines.images', 'parts'])

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
                                    'imei',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'device_name',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhereHas(

                                    'customer',

                                    function ($customerQuery)
                                    use ($search): void {

                                        $customerQuery

                                            ->where(
                                                'full_name',
                                                'like',
                                                "%{$search}%"
                                            )

                                            ->orWhere(
                                                'phone',
                                                'like',
                                                "%{$search}%"
                                            )

                                            ->orWhere(
                                                'cccd',
                                                'like',
                                                "%{$search}%"
                                            );
                                    }
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

            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($sortBy, $sortOrder)

            ->paginate(20)

            ->through(fn ($repair) => [

                'id' => $repair->id,

                'code' => $repair->code,

                'customer' => [

                    'id' =>
                        $repair->customer?->id,

                    'name' =>
                        $repair->customer?->full_name,

                    'phone' =>
                        $repair->customer?->phone,

                    'identity_card' =>
                        $repair->customer?->cccd,
                    'debt_balance' => (float) ($repair->customer?->debt_balance ?? 0),
                ],

                'device_name' =>
                    $repair->device_name,

                'contact_phone' => $repair->contact_phone,

                'screen_password' => $repair->screen_password,

                'screen_pattern' => $repair->screen_pattern,

                'serial' => $repair->serial,

                'account_type' => $repair->account_type,

                'account_email' => $repair->account_email,

                'account_password' => $repair->account_password,

                'repair_request' => $repair->repair_request,

                'issue' => $repair->issue,

                'estimated_cost' => $repair->estimated_cost,

                'final_cost' => $repair->final_cost,
                'parts_total' => $repair->parts_total,
                'labor_cost' => $repair->labor_cost,
                'surcharge' => $repair->surcharge,
                'paid_amount' => $repair->paid_amount,
                'change_amount' => $repair->change_amount,
                'payment_method' => $repair->payment_method,
                'payment_note' => $repair->payment_note,
                'parts' => $repair->parts->map(fn ($part) => [
                    'id' => $part->id,
                    'product_name' => $part->product_name,
                    'sku' => $part->sku,
                    'quantity' => $part->quantity,
                    'unit_price' => (float) $part->unit_price,
                    'subtotal' => (float) $part->subtotal,
                ]),

                'note' => $repair->note,

                'accessories' => $repair->accessories,

                'technician' => $repair->technician?->name,

                'images' => $repair->images->whereNull('timeline_id')->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => url('storage/' . $image->image_path),
                ]),

                'timelines' => $repair->timelines->map(fn ($timeline) => [
                    'id' => $timeline->id,
                    'title' => $timeline->title,
                    'description' => $timeline->description,
                    'status' => $timeline->status,
                    'issue' => $timeline->issue,
                    'parts_needed' => $timeline->parts_needed,
                    'waiting_for_parts' => (bool) $timeline->waiting_for_parts,
                    'expected_days' => $timeline->expected_days,
                    'images' => $timeline->images->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => url('storage/' . $image->image_path),
                    ]),
                    'created_at' => $timeline->created_at?->format('d/m/Y H:i'),
                    'user' => $timeline->user?->name,
                ]),

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
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ]
        );

        }

    /**
     * Gợi ý dữ liệu.
     */
    public function suggestions(): JsonResponse
    {
        // Khách hàng
        $customers = Customer::query()

        ->select([
            'id',
            'full_name as name',
            'phone',
            'cccd as identity_card',
        ])

        ->latest()

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

        $devices = Repair::query()->whereNotNull('device_name')->distinct()->orderBy('device_name')->limit(100)->pluck('device_name');
        $devices = $devices->merge(\App\Models\Product::query()->orderBy('name')->limit(200)->pluck('name'))
            ->merge(\App\Models\ProductImei::query()
            ->join('products', 'products.id', '=', 'product_imeis.product_id')
            ->select('products.name')->distinct()->orderBy('products.name')->limit(100)->pluck('products.name'))
            ->filter()->unique()->values();
        $imeis = \App\Models\ProductImei::query()->whereNotNull('imei')->orderByDesc('sold_at')->limit(100)->pluck('imei');

        return response()->json([
            'issues' => $issues,
            'accessories' => $accessories,
            'customers' => $customers,
            'devices' => $devices,
            'imeis' => $imeis,
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

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */

            $customer = $request->customer_id
                ? Customer::query()->findOrFail($request->customer_id)
                : ($request->filled('customer_phone')
                    ? Customer::query()->where('phone', $request->customer_phone)->first()
                    : null);

            if (!$customer) {
                $customer = app(\App\Services\CustomerService::class)->create([
                    'full_name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'cccd' => $request->identity_card,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Repair
            |--------------------------------------------------------------------------
            */

            $repair = Repair::query()

                ->create([

                    'code' =>
                        'SC-' . now()->format('YmdHis'),

                    'customer_id' =>
                        $customer->id,

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

                    'repair_request' =>
                        $request->repair_request,

                    'accessories' =>
                        $request->accessories,

                    'estimated_cost' =>
                        $request->estimated_cost,

                    'note' =>
                        $request->note,

                    'status' =>
                        'pending',

                    'received_at' =>
                        now(),
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

            $this->notifyRepairEvent(
                $repair,
                'Tiếp nhận máy sửa mới',
                ($customer->full_name ?: $customer->phone) . ' vừa gửi tiếp nhận ' . $repair->device_name . ' (' . $repair->code . ').'
            );

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

        $keyword = trim(
            (string) $request->keyword
        );

        if ($keyword === '') {

            return response()->json([]);
        }

        $customers = Customer::query()

            ->where(

                function ($query)
                use ($keyword): void {

                    $query

                        ->where(
            'full_name as name',
                            'like',
                            '%' . $keyword . '%'
                        )

                        ->orWhere(
                            'phone',
                            'like',
                            '%' . $keyword . '%'
                        )

                        ->orWhere(
                            'identity_card',
                            'like',
                            '%' . $keyword . '%'
                        );
                }
            )

            ->latest()

            ->limit(10)

            ->get();

        return response()->json(
            $customers
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
            'status' => ['sometimes', 'required', 'in:repairing,cancelled'],
            'description' => ['nullable', 'string', 'max:1000'],
            'issue' => ['nullable', 'array'],
            'issue.*' => ['string', 'max:255'],
            'waiting_for_parts' => ['nullable', 'boolean'],
            'parts_needed' => ['required_if:waiting_for_parts,1', 'nullable', 'string', 'max:1000'],
            'expected_days' => ['required_if:waiting_for_parts,1', 'nullable', 'integer', 'min:0', 'max:365'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
        ]);

        // A receipt accepted into the workshop advances to repairing by default.
        // The default also keeps older clients from failing when they omit status.
        $status = (string) $request->input('status', 'repairing');
        if (! in_array($repair->status, ['pending', 'repairing'], true)) {
            throw ValidationException::withMessages(['status' => 'Phiếu này không còn trong giai đoạn tiếp nhận hoặc đang sửa.']);
        }

        $progressIssues = $request->input('issue', []);
        $repairChanges = ['status' => $status];
        if ($progressIssues !== []) {
            $repairChanges['issue'] = array_values(array_unique(array_merge($repair->issue ?? [], $progressIssues)));
        }
        $repair->update($repairChanges);

        /*
        |--------------------------------------------------------------------------
        | Tiêu đề timeline
        |--------------------------------------------------------------------------
        */

        $titles = [
            'repairing' => 'Đang sửa',
            'cancelled' => 'Đã hủy phiếu sửa',
        ];

        $timeline = RepairTimeline::create([

            'repair_id' =>
                $repair->id,

            'user_id' =>
                auth()->id(),

            'status' => $status,

            'title' =>
                $titles[$status]
                ?? 'Cập nhật trạng thái',

            'description' =>
                $request->input('description'),
            'issue' => $request->input('issue', []),
            'parts_needed' => $request->input('parts_needed'),
            'expected_days' => $request->input('expected_days'),
            'waiting_for_parts' => $request->boolean('waiting_for_parts'),
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('repairs', 'public');
                RepairImage::create([
                    'repair_id' => $repair->id,
                    'timeline_id' => $timeline->id,
                    'image_path' => $path,
                ]);
            }
        }

        $statusLabel = $status === 'cancelled' ? 'đã hủy phiếu' : 'đang sửa';
        $this->notifyRepairEvent($repair, 'Cập nhật phiếu sửa ' . $repair->code, 'Phiếu ' . $repair->code . ' chuyển sang trạng thái ' . $statusLabel . '.', $status === 'cancelled' ? 'warning' : 'repair');

        return back();
    }

    /** Hoàn tất sửa, ghi nhận linh kiện và trừ kho. */
    public function complete(Request $request, Repair $repair): JsonResponse
    {
        $data = $request->validate([
            'parts' => ['nullable', 'array'],
            'parts.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'parts.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'parts.*.quantity' => ['required', 'integer', 'min:1'],
            'parts.*.unit_price' => ['required', 'numeric', 'min:0'],
            'labor_cost' => ['required', 'numeric', 'min:0'],
            'surcharge' => ['required', 'numeric', 'min:0'],
        ]);

        if ($repair->status !== 'repairing') {
            throw ValidationException::withMessages(['status' => 'Chỉ phiếu đang sửa mới có thể hoàn tất.']);
        }

        $completed = DB::transaction(function () use ($repair, $data): Repair {
            $repair = Repair::query()->lockForUpdate()->findOrFail($repair->id);
            if ($repair->status !== 'repairing') {
                throw ValidationException::withMessages(['status' => 'Phiếu sửa đã được cập nhật ở nơi khác.']);
            }

            $repair->parts()->delete();
            $partsTotal = 0.0;
            foreach ($data['parts'] ?? [] as $line) {
                $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);
                if (! $product->is_active || $product->product_type === 'imei' || $product->manage_stock_by_serial) {
                    throw ValidationException::withMessages(['parts' => "Sản phẩm {$product->name} không thể dùng làm linh kiện xuất kho."]);
                }

                $variantId = $line['variant_id'] ?? null;
                $hasVariants = $product->variants()->where('is_active', true)->exists();
                $variant = null;
                if ($variantId) {
                    $variant = ProductVariant::query()->where('product_id', $product->id)->where('is_active', true)->lockForUpdate()->findOrFail($variantId);
                    if ($variant->stock < $line['quantity'] || $product->stock < $line['quantity']) {
                        throw ValidationException::withMessages(['parts' => "Tồn kho của {$product->name} không đủ."]);
                    }
                    $variant->decrement('stock', $line['quantity']);
                    $product->decrement('stock', $line['quantity']);
                } else {
                    if ($hasVariants) {
                        throw ValidationException::withMessages(['parts' => "Vui lòng chọn phân loại cho {$product->name}."]);
                    }
                    if ($product->stock < $line['quantity']) {
                        throw ValidationException::withMessages(['parts' => "Tồn kho của {$product->name} không đủ."]);
                    }
                    $product->decrement('stock', $line['quantity']);
                }

                $unitPrice = (float) $line['unit_price'];
                $subtotal = $unitPrice * (int) $line['quantity'];
                $partsTotal += $subtotal;
                RepairPart::query()->create([
                    'repair_id' => $repair->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name . ($variant ? ' · ' . collect($variant->attributes ?? [])->values()->implode(' / ') : ''),
                    'sku' => $variant?->sku ?: $product->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $labor = (float) $data['labor_cost'];
            $surcharge = (float) $data['surcharge'];
            $total = $partsTotal + $labor + $surcharge;
            $repair->update([
                'parts_total' => $partsTotal,
                'labor_cost' => $labor,
                'surcharge' => $surcharge,
                'final_cost' => $total,
                'status' => 'done',
                'completed_at' => now(),
            ]);
            RepairTimeline::query()->create([
                'repair_id' => $repair->id,
                'user_id' => auth()->id(),
                'status' => 'done',
                'title' => 'Hoàn tất sửa',
                'description' => 'Linh kiện: ' . number_format($partsTotal, 0, ',', '.') . 'đ · Công sửa: ' . number_format($labor, 0, ',', '.') . 'đ · Phụ phí: ' . number_format($surcharge, 0, ',', '.') . 'đ · Thành tiền: ' . number_format($total, 0, ',', '.') . 'đ',
            ]);
            return $repair;
        });

        $this->notifyRepairEvent($completed, 'Đã hoàn tất sửa chữa', 'Phiếu ' . $completed->code . ' (' . $completed->device_name . ') đã hoàn tất, tổng chi phí ' . number_format((float) $completed->final_cost, 0, ',', '.') . ' đ.');

        return response()->json(['success' => true, 'repair_id' => $completed->id]);
    }

    /** Thu tiền theo modal POS và chuyển sang đã trả khách. */
    public function returnToCustomer(Request $request, Repair $repair): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,bank,card'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'pay_old_debt' => ['nullable', 'boolean'],
        ]);
        if ($repair->status !== 'done') {
            throw ValidationException::withMessages(['status' => 'Chỉ phiếu đã hoàn tất sửa mới có thể trả khách.']);
        }

        DB::transaction(function () use ($repair, $data): void {
            $repair = Repair::query()->with('customer')->lockForUpdate()->findOrFail($repair->id);
            if ($repair->status !== 'done') {
                throw ValidationException::withMessages(['status' => 'Phiếu sửa đã được cập nhật ở nơi khác.']);
            }
            $customer = $repair->customer;
            if ($customer) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            }
            $total = (float) $repair->final_cost;
            $paid = (float) $data['paid_amount'];
            if (! $customer && $paid < $total) {
                throw ValidationException::withMessages(['paid_amount' => 'Khách lẻ cần thanh toán đủ trước khi trả máy.']);
            }

            $newDebt = $customer ? max(0, $total - min($total, $paid)) : 0;
            $oldDebtPaid = $customer && ($data['pay_old_debt'] ?? false)
                ? min((float) $customer->debt_balance, max(0, $paid - $total))
                : 0;
            if ($newDebt > 0) {
                CustomerDebt::query()->create([
                    'customer_id' => $customer->id,
                    'type' => 'increase',
                    'amount' => $newDebt,
                    'source_type' => Repair::class,
                    'source_id' => $repair->id,
                    'note' => 'Còn thiếu khi thanh toán phiếu sửa ' . $repair->code,
                ]);
                $customer->increment('debt_balance', $newDebt);
            }
            if ($oldDebtPaid > 0) {
                CustomerDebt::query()->create([
                    'customer_id' => $customer->id,
                    'type' => 'decrease',
                    'amount' => $oldDebtPaid,
                    'source_type' => Repair::class,
                    'source_id' => $repair->id,
                    'note' => 'Trích tiền thừa phiếu sửa ' . $repair->code . ' để thanh toán nợ cũ',
                ]);
                $customer->decrement('debt_balance', $oldDebtPaid);
            }
            $change = max(0, $paid - $total - $oldDebtPaid);
            $repair->update([
                'status' => 'returned',
                'paid_amount' => $paid,
                'change_amount' => $change,
                'payment_method' => $data['payment_method'],
                'payment_note' => $data['note'] ?? null,
                'returned_at' => now(),
            ]);
            RepairTimeline::query()->create([
                'repair_id' => $repair->id,
                'user_id' => auth()->id(),
                'status' => 'returned',
                'title' => 'Đã trả khách',
                'description' => 'Đã thu ' . number_format($paid, 0, ',', '.') . 'đ · ' . $data['payment_method'] . ($change > 0 ? ' · Tiền thừa ' . number_format($change, 0, ',', '.') . 'đ' : '') . (! empty($data['note']) ? ' · ' . $data['note'] : ''),
            ]);
        });

        $repair->refresh();
        $this->notifyRepairEvent($repair, 'Đã trả máy cho khách', 'Phiếu ' . $repair->code . ' (' . $repair->device_name . ') đã thanh toán và trả khách.');

        return response()->json(['success' => true]);
    }

    private function notifyRepairEvent(Repair $repair, string $title, string $message, string $type = 'repair'): void
    {
        Notification::send(
            User::query()->get(),
            new SystemNotification($title, $message, $type, route('repairs.index'))
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
     * Cập nhật phiếu sửa chữa.
     */
    public function update(
        Request $request,
        Repair $repair
    ): RedirectResponse {

        $validated = $request->validate([

            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($request->input('customer_id'))],
            'identity_card' => ['nullable', 'string', 'max:20'],
            'contact_phone' => ['nullable', 'string', 'max:20'],

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

            'serial' => ['nullable', 'string', 'max:255'],
            'screen_password' => ['nullable', 'string', 'max:255'],
            'screen_pattern' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'string', 'max:255'],
            'account_email' => ['nullable', 'string', 'max:255'],
            'account_password' => ['nullable', 'string', 'max:255'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', 'max:255'],

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
                'nullable', 'numeric', 'min:0',
            ],

            'images.*' => [
                'nullable',
                'image',
                'max:5120',
            ],
        ]);

        DB::beginTransaction();

        try {

            $customer = !empty($validated['customer_id'])
                ? Customer::query()->findOrFail($validated['customer_id'])
                : (!empty($validated['customer_phone'])
                    ? Customer::query()->where('phone', $validated['customer_phone'])->first()
                    : null);

            if (!$customer) {
                $customer = app(\App\Services\CustomerService::class)->create([
                    'full_name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'] ?? null,
                    'cccd' => $validated['identity_card'] ?? null,
                ]);
            } else {
                $customer->update([
                    'full_name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'] ?? null,
                    'cccd' => $validated['identity_card'] ?? null,
                ]);
            }

            $repair->update([

                'customer_id' => $customer->id,
                'contact_phone' => $validated['contact_phone'] ?? null,

                'device_name' =>
                    $validated['device_name'],

                'imei' =>
                    $validated['imei'] ?? null,

                'serial' => $validated['serial'] ?? null,

                'screen_password' => $validated['screen_password'] ?? null,

                'screen_pattern' => $validated['screen_pattern'] ?? null,

                'account_type' => $validated['account_type'] ?? null,

                'account_email' => $validated['account_email'] ?? null,

                'account_password' => $validated['account_password'] ?? null,

                'issue' =>
                    $validated['issue'] ?? [],

                'accessories' => $validated['accessories'] ?? [],

                'repair_request' =>
                    $validated['repair_request']
                    ?? null,

                'note' =>
                    $validated['note']
                    ?? null,

                'estimated_cost' =>
                    $validated['estimated_cost']
                    ?? null,

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

                'status' => $repair->status,

                'title' =>
                    'Cập nhật phiếu sửa',

                'description' =>
                    'Kỹ thuật viên cập nhật thông tin sửa chữa',
            ]);

            DB::commit();

            return back();

        } catch (\Throwable $e) {

            DB::rollBack();

            throw $e;
        }
    }
}
