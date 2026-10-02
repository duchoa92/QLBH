<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        $results = collect();
        $user = $request->user();

        if ($user->can('products.view')) {
            $results = $results->concat(Product::query()
                ->with(['category:id,name', 'brand:id,name'])
                ->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhere('search_text', 'like', $like)
                        ->orWhereHas('imeis', fn ($imei) => $imei
                            ->where('imei', 'like', $like)
                            ->orWhere('serial', 'like', $like));
                })
                ->limit(5)->get()
                ->map(fn (Product $product) => $this->result(
                    'Sản phẩm', $product->name,
                    collect([$product->sku, $product->barcode, $product->brand?->name])->filter()->join(' · '),
                    '/products/' . $product->id
                )));

            $imeis = ProductImei::query()
                ->with('product:id,name')
                ->where(fn ($query) => $query->where('imei', 'like', $like)->orWhere('serial', 'like', $like))
                ->limit(4)->get()
                ->map(fn (ProductImei $imei) => $this->result(
                    'IMEI / Serial', $imei->imei ?: $imei->serial,
                    $imei->product?->name ?: 'Sản phẩm', '/imeis/' . $imei->id
                ));
            $results = $results->concat($imeis);
        }

        $results = $results->concat(Customer::query()
            ->where(fn ($query) => $query->where('full_name', 'like', $like)
                ->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like)->orWhere('code', 'like', $like))
            ->limit(5)->get()
            ->map(fn (Customer $customer) => $this->result(
                'Khách hàng', $customer->full_name,
                collect([$customer->phone, $customer->code])->filter()->join(' · '),
                '/customers/' . $customer->id
            )));

        $results = $results->concat(Supplier::query()
            ->where(fn ($query) => $query->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like)->orWhere('code', 'like', $like))
            ->limit(5)->get()
            ->map(fn (Supplier $supplier) => $this->result(
                'Nhà cung cấp', $supplier->name,
                collect([$supplier->phone, $supplier->code])->filter()->join(' · '),
                '/suppliers/' . $supplier->id
            )));

        $results = $results->concat(Sale::query()
            ->with('customer:id,full_name,phone')
            ->where(fn ($query) => $query->where('code', 'like', $like)->orWhereHas('customer', fn ($customer) => $customer
                ->where('full_name', 'like', $like)->orWhere('phone', 'like', $like)))
            ->latest('id')->limit(5)->get()
            ->map(fn (Sale $sale) => $this->result(
                'Hóa đơn', $sale->code,
                collect([$sale->customer?->full_name, number_format((float) $sale->grand_total, 0, ',', '.') . ' đ'])->filter()->join(' · '),
                '/sales/' . $sale->id
            )));

        $results = $results->concat(StockImport::query()
            ->with('supplier:id,name')
            ->where(fn ($query) => $query->where('code', 'like', $like)->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $like)))
            ->latest('id')->limit(5)->get()
            ->map(fn (StockImport $import) => $this->result(
                'Phiếu nhập', $import->code,
                collect([$import->supplier?->name, $import->import_date?->format('d/m/Y')])->filter()->join(' · '),
                '/stock-import/' . $import->id
            )));

        $results = $results->concat(Repair::query()
            ->where(fn ($query) => $query->where('code', 'like', $like)
                ->orWhere('contact_phone', 'like', $like)
                ->orWhere('device_name', 'like', $like)->orWhere('imei', 'like', $like)->orWhere('serial', 'like', $like))
            ->latest('id')->limit(5)->get()
            ->map(fn (Repair $repair) => $this->result(
                'Phiếu sửa chữa', $repair->code ?: $repair->device_name,
                collect([$repair->contact_phone, $repair->device_name])->filter()->join(' · '),
                '/repairs/' . $repair->id
            )));

        if ($user->can('categories.view')) {
            $results = $results->concat(Category::query()->where('name', 'like', $like)->limit(4)->get()
                ->map(fn (Category $category) => $this->result('Danh mục', $category->name, 'Danh mục sản phẩm', '/categories')));
        }

        if ($user->can('brands.view')) {
            $results = $results->concat(Brand::query()->with('category:id,name')->where('name', 'like', $like)->limit(4)->get()
                ->map(fn (Brand $brand) => $this->result('Thương hiệu', $brand->name, $brand->category?->name, '/brands')));
        }

        $results = $results->concat(Unit::query()->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('short_name', 'like', $like))
            ->limit(4)->get()->map(fn (Unit $unit) => $this->result('Đơn vị tính', $unit->name, $unit->short_name, '/settings')));

        if ($user->can('users.view')) {
            $results = $results->concat(User::query()
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(4)->get()
                ->map(fn (User $foundUser) => $this->result('Nhân viên', $foundUser->name, $foundUser->email, '/users')));
        }

        return response()->json(['results' => $results->take(40)->values()]);
    }

    private function result(string $type, ?string $label, ?string $detail, string $url): array
    {
        return [
            'type' => $type,
            'label' => $label ?: $type,
            'detail' => $detail ?: '',
            'url' => $url,
        ];
    }
}
