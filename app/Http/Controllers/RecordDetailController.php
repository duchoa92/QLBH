<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Repair;
use App\Models\Sale;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
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
            return response()->json(compact('type', 'title', 'subtitle', 'rows'));
        }

        return Inertia::render('Records/Show', compact('type', 'title', 'subtitle', 'rows'));
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
        $r = Sale::with(['customer:id,full_name', 'user:id,name'])->findOrFail($id);
        return ['Hóa đơn', $r->code, $this->rows([
            'Khách hàng' => $r->customer?->full_name, 'Nhân viên' => $r->user?->name,
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
        $r = Repair::findOrFail($id);
        return ['Phiếu sửa chữa', $r->code ?: $r->device_name, $this->rows([
            'Khách hàng' => $r->customer_name, 'Điện thoại' => $r->contact_phone ?: $r->customer_phone,
            'Thiết bị' => $r->device_name, 'IMEI / Serial' => $r->imei ?: $r->serial,
            'Yêu cầu sửa chữa' => $r->repair_request, 'Ngày nhận' => $this->date($r->received_at),
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
        $r = Brand::with('category:id,name')->findOrFail($id);
        return ['Thương hiệu', null, $this->rows(['Tên thương hiệu' => $r->name, 'Danh mục' => $r->category?->name])];
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
        return $value ? $value->format('d/m/Y H:i') : null;
    }
}
