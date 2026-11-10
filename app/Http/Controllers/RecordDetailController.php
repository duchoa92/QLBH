<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\CustomerPoint;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Repair;
use App\Models\Sale;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\SupplierDebt;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecordDetailController extends Controller
{
    public function show(Request $request, string $type, int $id): Response|JsonResponse
    {
        $permission = match ($type) {
            'product', 'imei' => 'products.view',
            'customer' => 'customers.view',
            'supplier' => 'suppliers.view',
            'sale' => 'sales.view',
            'stock-import' => 'stock_imports.view',
            'repair' => 'repairs.view',
            'category' => 'categories.view',
            'brand' => 'brands.view',
            'unit' => 'settings.view',
            'user' => 'users.view',
            default => abort(404),
        };
        abort_unless($request->user()->can($permission), 403);

        [$title, $subtitle, $rows] = match ($type) {
            'product' => $this->product($id),
            'imei' => $this->imei($id),
            'customer' => $this->customer($id),
            'supplier' => $this->supplier($id),
            'sale' => $this->sale($id),
            'stock-import' => $this->stockImport($id),
            'repair' => $this->repair($id),
            'category' => $this->category($id),
            'brand' => $this->brand($id),
            'unit' => $this->unit($id),
            'user' => $this->user($id),
        };

        if ($request->expectsJson()) {
            // Khách hàng, nhà cung cấp, nhân viên có thêm `data` (số liệu, giao dịch, công nợ...) cho modal chi tiết trực quan.
            $data = match ($type) {
                'customer' => $this->customerData($id, $request->user()),
                'supplier' => $this->supplierData($id, $request->user()),
                'user' => $this->userData($id, $request->user()),
                default => null,
            };

            return response()->json(compact('type', 'title', 'subtitle', 'rows', 'data'));
        }

        return Inertia::render('Records/Show', compact('type', 'title', 'subtitle', 'rows'));
    }

    /* ------------------------------------------------------------------
     | Dữ liệu cho modal chi tiết khách hàng / nhà cung cấp / nhân viên
     | Phần nào người xem không có quyền thì trả null để giao diện ẩn đi.
     ------------------------------------------------------------------ */

    private function customerData(int $id, User $viewer): array
    {
        $customer = Customer::with('devices:id,customer_id,brand,model,imei,color')->findOrFail($id);
        $saleColumns = ['id', 'code', 'status', 'grand_total', 'paid_amount', 'created_at'];

        $sales = $viewer->can('sales.view')
            ? Sale::query()->where('customer_id', $id)->where('status', '!=', 'cancelled')
                ->latest()->limit(10)->get($saleColumns)
                ->map(fn (Sale $sale) => $this->saleRow($sale))->values()->all()
            : null;

        $debt = null;
        if ($viewer->can('customer_debts.view')) {
            $debt = [
                'orders' => Sale::query()->where('customer_id', $id)->where('status', 'completed')
                    ->whereRaw('grand_total > paid_amount')->orderBy('created_at')->get($saleColumns)
                    ->map(fn (Sale $sale) => $this->saleRow($sale))->values()->all(),
                'history' => CustomerDebt::query()->with('source')->where('customer_id', $id)
                    ->latest()->limit(20)->get()
                    ->map(fn (CustomerDebt $entry) => [
                        'id' => $entry->id,
                        'type' => $entry->type,
                        'amount' => (float) $entry->amount,
                        'method' => $entry->payment_method,
                        'note' => $entry->note,
                        'code' => $entry->source?->code,
                        'date' => $this->date($entry->created_at),
                    ])->values()->all(),
            ];
        }

        return [
            'id' => $customer->id,
            'code' => $customer->code,
            'name' => $customer->full_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'cccd' => $customer->cccd,
            'birthday' => $customer->birthday?->format('d/m/Y'),
            'gender' => $customer->gender,
            'address' => collect([$customer->address, $customer->ward, $customer->district, $customer->province])->filter()->join(', '),
            'note' => $customer->note,
            'type' => $customer->customer_type,
            'blocked' => $customer->isBlocked(),
            'created_at' => $this->date($customer->created_at),
            'stats' => [
                'debt_balance' => (float) $customer->debt_balance,
                'point_balance' => (float) $customer->point_balance,
                'total_spent' => (float) $customer->total_spent,
                'total_orders' => (int) $customer->total_orders,
                'last_order_at' => $this->date($customer->last_order_at),
            ],
            'sales' => $sales,
            'debt' => $debt,
            'points' => CustomerPoint::query()->where('customer_id', $id)->latest()->limit(15)->get()
                ->map(fn (CustomerPoint $point) => [
                    'id' => $point->id,
                    'type' => $point->type,
                    'points' => (int) $point->points,
                    'note' => $point->note,
                    'date' => $this->date($point->created_at),
                ])->values()->all(),
            'devices' => $customer->devices->map(fn ($device) => [
                'id' => $device->id,
                'name' => trim(($device->brand ?? '') . ' ' . ($device->model ?? '')),
                'imei' => $device->imei,
                'color' => $device->color,
            ])->values()->all(),
        ];
    }

    private function supplierData(int $id, User $viewer): array
    {
        $supplier = Supplier::findOrFail($id);
        $importColumns = ['id', 'code', 'import_date', 'grand_total', 'paid_amount', 'status'];

        $imports = null;
        $stats = ['import_count' => null, 'total_import_value' => null, 'last_import_at' => null];
        if ($viewer->can('stock_imports.view')) {
            $completed = StockImport::query()->where('supplier_id', $id)->where('status', 'completed');
            $stats = [
                'import_count' => (clone $completed)->count(),
                'total_import_value' => (float) (clone $completed)->sum('grand_total'),
                'last_import_at' => $this->date((clone $completed)->max('import_date')),
            ];
            $imports = StockImport::query()->where('supplier_id', $id)->where('status', '!=', 'cancelled')
                ->orderByDesc('import_date')->limit(10)->get($importColumns)
                ->map(fn (StockImport $import) => $this->importRow($import))->values()->all();
        }

        $debt = null;
        if ($viewer->can('supplier_debts.view')) {
            $debt = [
                'orders' => StockImport::query()->where('supplier_id', $id)->where('status', 'completed')
                    ->whereRaw('grand_total > paid_amount')->orderBy('import_date')->get($importColumns)
                    ->map(fn (StockImport $import) => $this->importRow($import))->values()->all(),
                'history' => SupplierDebt::query()->with(['source', 'user:id,name'])->where('supplier_id', $id)
                    ->latest()->limit(20)->get()
                    ->map(fn (SupplierDebt $entry) => [
                        'id' => $entry->id,
                        'type' => $entry->type,
                        'amount' => (float) $entry->amount,
                        'method' => $entry->payment_method,
                        'note' => $entry->note,
                        'code' => $entry->source?->code,
                        'by' => $entry->user?->name,
                        'date' => $this->date($entry->created_at),
                    ])->values()->all(),
            ];
        }

        // Tài khoản ngân hàng: ưu tiên danh sách nhiều tài khoản, không có thì dùng trường cũ.
        $accounts = collect($supplier->bank_accounts ?? [])
            ->map(fn ($account) => [
                'bank' => $account['bank_name'] ?? null,
                'number' => $account['bank_account'] ?? null,
                'holder' => $account['bank_account_name'] ?? null,
            ])
            ->filter(fn ($account) => filled($account['number']))->values();
        if ($accounts->isEmpty() && filled($supplier->bank_account)) {
            $accounts = collect([[
                'bank' => $supplier->bank_name,
                'number' => $supplier->bank_account,
                'holder' => $supplier->bank_account_name,
            ]]);
        }

        return [
            'id' => $supplier->id,
            'code' => $supplier->code,
            'name' => $supplier->name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'contact_person' => $supplier->contact_person,
            'tax_code' => $supplier->tax_code,
            'address' => collect([$supplier->address, $supplier->ward, $supplier->district, $supplier->province])->filter()->join(', '),
            'note' => $supplier->note,
            'active' => (bool) $supplier->is_active,
            'created_at' => $this->date($supplier->created_at),
            'bank_accounts' => $accounts->all(),
            'stats' => ['debt_balance' => (float) $supplier->debt_balance] + $stats,
            'imports' => $imports,
            'debt' => $debt,
        ];
    }

    private function userData(int $id, User $viewer): array
    {
        $user = User::with('roles:id,name')->findOrFail($id);

        $stats = null;
        $sales = null;
        if ($viewer->can('sales.view')) {
            $completed = Sale::query()->where('user_id', $id)->where('status', 'completed');
            $stats = [
                'sales_count' => (clone $completed)->count(),
                'revenue' => (float) (clone $completed)->sum('grand_total'),
                'revenue_30d' => (float) (clone $completed)->where('created_at', '>=', now()->subDays(30))->sum('grand_total'),
                'last_sale_at' => $this->date((clone $completed)->max('created_at')),
            ];
            $sales = Sale::query()->with('customer:id,full_name')->where('user_id', $id)->where('status', '!=', 'cancelled')
                ->latest()->limit(6)->get(['id', 'code', 'customer_id', 'status', 'grand_total', 'paid_amount', 'created_at'])
                ->map(fn (Sale $sale) => $this->saleRow($sale) + ['customer' => $sale->customer?->full_name])->values()->all();
        }

        $shifts = $viewer->can('pos.access')
            ? WorkShift::query()->where('user_id', $id)->latest('opened_at')->limit(5)->get()
                ->map(fn (WorkShift $shift) => [
                    'id' => $shift->id,
                    'opened_at' => $this->date($shift->opened_at),
                    'closed_at' => $this->date($shift->closed_at),
                    'open' => $shift->closed_at === null,
                    'opening_amount' => (float) $shift->opening_amount,
                    'closing_amount' => $shift->closing_amount === null ? null : (float) $shift->closing_amount,
                ])->values()->all()
            : null;

        // Quyền gom theo chức năng (phần trước dấu chấm), vd. sales.view + sales.edit => sales: [view, edit]
        $groups = $user->getAllPermissions()->pluck('name')
            ->groupBy(fn ($name) => explode('.', $name)[0])
            ->map(fn ($names, $group) => [
                'group' => $group,
                'actions' => $names->map(fn ($name) => explode('.', $name)[1] ?? $name)->values()->all(),
            ])->values()->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'phone' => $user->phone,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'created_at' => $this->date($user->created_at),
            'permission_count' => collect($groups)->sum(fn ($group) => count($group['actions'])),
            'permission_groups' => $groups,
            'stats' => $stats,
            'sales' => $sales,
            'shifts' => $shifts,
        ];
    }

    private function saleRow(Sale $sale): array
    {
        $total = (float) $sale->grand_total;
        $paid = (float) $sale->paid_amount;

        return [
            'id' => $sale->id,
            'code' => $sale->code,
            'date' => $this->date($sale->created_at),
            'status' => $sale->status,
            'total' => $total,
            'paid' => $paid,
            'due' => max(0, $total - $paid),
            'href' => route('sales.show', $sale),
        ];
    }

    private function importRow(StockImport $import): array
    {
        $total = (float) $import->grand_total;
        $paid = (float) $import->paid_amount;

        return [
            'id' => $import->id,
            'code' => $import->code,
            'date' => $this->date($import->import_date),
            'status' => $import->status,
            'total' => $total,
            'paid' => $paid,
            'due' => max(0, $total - $paid),
            'href' => route('stock.show', $import),
        ];
    }

    private function product(int $id): array
    {
        $r = Product::with(['category:id,name', 'brand:id,name', 'unit:id,name,short_name'])->findOrFail($id);
        return ['Sản phẩm', $r->sku, $this->rows([
            'Mã sản phẩm' => $r->sku, 'Mã vạch' => $r->barcode, 'Danh mục' => $r->category?->name,
            'Thương hiệu' => $r->brand?->name, 'Giá nhập' => $this->money($r->cost_price),
            'Giá bán' => $this->money($r->sell_price), 'Tồn kho' => trim(($r->stock ?? 0).' '.($r->unit?->short_name ?: $r->unit?->name)),
            'Trạng thái' => $r->is_active ? 'Đang kinh doanh' : 'Ngừng kinh doanh',
        ])];
    }

    private function imei(int $id): array
    {
        $r = ProductImei::with('product:id,name')->findOrFail($id);
        return ['IMEI / Serial', $r->product?->name, $this->rows([
            'IMEI' => $r->imei, 'Serial' => $r->serial, 'Sản phẩm' => $r->product?->name,
            'Trạng thái' => $r->status, 'Ngày bán' => $this->date($r->sold_at),
        ])];
    }

    private function customer(int $id): array
    {
        $r = Customer::findOrFail($id);
        return ['Khách hàng', $r->code, $this->rows([
            'Mã khách hàng' => $r->code, 'Điện thoại' => $r->phone, 'Email' => $r->email,
            'Loại khách hàng' => $r->customer_type, 'Địa chỉ' => $r->address,
            'Điểm tích lũy' => $r->point_balance, 'Công nợ' => $this->money($r->debt_balance),
        ])];
    }

    private function supplier(int $id): array
    {
        $r = Supplier::findOrFail($id);
        return ['Nhà cung cấp', $r->code, $this->rows([
            'Mã nhà cung cấp' => $r->code, 'Điện thoại' => $r->phone, 'Email' => $r->email,
            'Địa chỉ' => $r->address, 'Công nợ' => $this->money($r->debt_balance),
        ])];
    }

    private function sale(int $id): array
    {
        $r = Sale::with(['customer:id,full_name', 'user:id,name', 'items.product:id,name'])->findOrFail($id);
        return ['Hóa đơn', $r->code, $this->rows([
            'Khách hàng' => $r->customer?->full_name, 'Nhân viên' => $r->user?->name,
            'Sản phẩm' => $r->items->map(fn ($item) => trim(($item->product?->name ?: 'Sản phẩm') . ' × ' . $item->quantity))->join("\n"),
            'Ngày tạo' => $this->date($r->created_at), 'Tạm tính' => $this->money($r->subtotal),
            'Tổng thanh toán' => $this->money($r->grand_total), 'Đã thanh toán' => $this->money($r->paid_amount),
            'Tiền mặt nhận' => $this->money($r->cash_received_amount), 'Chuyển khoản nhận' => $this->money($r->bank_received_amount),
            'Trạng thái' => $r->status,
        ])];
    }

    private function stockImport(int $id): array
    {
        $r = StockImport::with('supplier:id,name')->findOrFail($id);
        return ['Phiếu nhập hàng', $r->code, $this->rows([
            'Nhà cung cấp' => $r->supplier?->name ?: $r->party_name, 'Ngày nhập' => $this->date($r->import_date),
            'Tổng tiền' => $this->money($r->grand_total ?? $r->total_amount), 'Đã thanh toán' => $this->money($r->paid_amount),
            'Trạng thái' => $r->status,
        ])];
    }

    private function repair(int $id): array
    {
        $r = Repair::with(['customer:id,full_name', 'parts:id,repair_id,product_name,quantity'])->findOrFail($id);
        return ['Phiếu sửa chữa', $r->code ?: $r->device_name, $this->rows([
            'Khách hàng' => $r->customer?->full_name, 'Điện thoại' => $r->contact_phone ?: $r->customer?->phone,
            'Thiết bị' => $r->device_name, 'IMEI / Serial' => $r->imei ?: $r->serial,
            'Yêu cầu sửa chữa' => $r->repair_request,
            'Linh kiện' => $r->parts->map(fn ($part) => trim($part->product_name . ' × ' . $part->quantity))->join("\n"), 'Ngày nhận' => $this->date($r->received_at),
            'Chi phí' => $this->money($r->final_cost ?: $r->estimated_cost), 'Trạng thái' => $r->status,
        ])];
    }

    private function category(int $id): array
    {
        $r = Category::findOrFail($id);
        return ['Danh mục', null, $this->rows(['Tên danh mục' => $r->name])];
    }

    private function brand(int $id): array
    {
        $r = Brand::with('categories:id,name')->findOrFail($id);
        return ['Thương hiệu', null, $this->rows(['Tên thương hiệu' => $r->name, 'Danh mục' => $r->categories->sortBy('name')->pluck('name')->join(', ')])];
    }

    private function unit(int $id): array
    {
        $r = Unit::findOrFail($id);
        return ['Đơn vị tính', null, $this->rows(['Tên đơn vị' => $r->name, 'Ký hiệu' => $r->short_name])];
    }

    private function user(int $id): array
    {
        $r = User::with('roles')->findOrFail($id);
        return ['Nhân viên', null, $this->rows([
            'Email' => $r->email, 'Điện thoại' => $r->phone, 'Vai trò' => $r->roles->pluck('name')->join(', '),
        ])];
    }

    private function rows(array $values): array
    {
        return collect($values)->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v, $label) => ['label' => $label, 'value' => (string) $v])->values()->all();
    }

    private function money(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 0, ',', '.').' đ';
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
    }
}
