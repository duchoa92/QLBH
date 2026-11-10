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
use App\Support\FuzzyMatcher;
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

        $tokens = FuzzyMatcher::tokens($term);
        if ($tokens === []) {
            return response()->json(['results' => []]);
        }

        $results = collect();
        $user = $request->user();

        if ($user->can('products.view')) {
            $results = $results->concat($this->search(
                Product::query()->with(['category:id,name', 'brand:id,name']),
                $tokens, ['name', 'sku', 'model', 'search_text'],
                ['imeis' => ['imei', 'serial']], 5,
                fn (Product $p) => [$p->name, $p->sku, $p->model, $p->brand?->name, $p->category?->name],
                fn (Product $p) => $this->result('Sản phẩm', 'product', $p->id, $p->name,
                    collect([$p->sku, $p->model, $p->brand?->name])->filter()->join(' · '),
                    route('products.index', ['open_product' => $p->id], false), ['name' => $p->name]),
            ));

            $results = $results->concat($this->search(
                ProductImei::query()->with('product:id,name'),
                $tokens, ['imei', 'serial'], [], 4,
                fn (ProductImei $i) => [$i->imei, $i->serial, $i->product?->name],
                fn (ProductImei $i) => $this->result('IMEI / Serial', 'imei', $i->id, $i->imei ?: $i->serial,
                    $i->product?->name ?: 'Sản phẩm', route('products.index', ['open_imei_product' => $i->product_id], false),
                    ['product_id' => $i->product_id, 'name' => $i->product?->name]),
            ));
        }

        if ($user->can('customers.view')) {
            $results = $results->concat($this->search(
                Customer::query(), $tokens, ['full_name', 'phone', 'email', 'code', 'search_text'], [], 5,
                fn (Customer $c) => [$c->full_name, $c->phone, $c->email, $c->code],
                fn (Customer $c) => $this->result('Khách hàng', 'customer', $c->id, $c->full_name,
                    collect([$c->phone, $c->code])->filter()->join(' · '),
                    route('customers.index', ['open_customer' => $c->id], false)),
            ));
        }

        if ($user->can('suppliers.view')) {
            $results = $results->concat($this->search(
                Supplier::query(), $tokens, ['name', 'phone', 'email', 'code', 'search_text'], [], 5,
                fn (Supplier $s) => [$s->name, $s->phone, $s->email, $s->code],
                fn (Supplier $s) => $this->result('Nhà cung cấp', 'supplier', $s->id, $s->name,
                    collect([$s->phone, $s->code])->filter()->join(' · '),
                    route('suppliers.index', ['open_supplier' => $s->id], false)),
            ));
        }

        if ($user->can('sales.view')) {
            $results = $results->concat($this->search(
                Sale::query()->with(['customer:id,full_name,phone', 'items.product:id,name,sku']),
                $tokens, ['code'],
                ['customer' => ['full_name', 'phone'], 'items.product' => ['name', 'sku', 'search_text']], 6,
                fn (Sale $s) => [$s->code, $s->customer?->full_name, $s->customer?->phone,
                    ...$s->items->map(fn ($item) => $item->product?->name)->all()],
                fn (Sale $s) => $this->result('Hóa đơn', 'sale', $s->id, $s->code,
                    $this->summary(
                        $s->customer?->full_name ? 'Khách: ' . $s->customer->full_name : 'Khách lẻ',
                        number_format((float) $s->grand_total, 0, ',', '.') . ' đ',
                        $this->matchedItems($s->items->map(fn ($item) => $item->product?->name)->all(), $tokens),
                    ),
                    route('sales.index', ['open_sale' => $s->id], false)),
                true,
            ));
        }

        if ($user->can('stock_imports.view')) {
            $results = $results->concat($this->search(
                StockImport::query()->with('supplier:id,name'),
                $tokens, ['code'], ['supplier' => ['name']], 5,
                fn (StockImport $i) => [$i->code, $i->supplier?->name],
                fn (StockImport $i) => $this->result('Phiếu nhập', 'stock-import', $i->id, $i->code,
                    collect([$i->supplier?->name, $i->import_date?->format('d/m/Y')])->filter()->join(' · '),
                    route('stock.index', ['open_import' => $i->id], false)),
                true,
            ));
        }

        if ($user->can('repairs.view')) {
            $results = $results->concat($this->search(
                Repair::query()->with(['customer:id,full_name,phone', 'parts:id,repair_id,product_name']),
                $tokens,
                ['code', 'contact_phone', 'device_name', 'imei', 'serial', 'repair_request'],
                ['customer' => ['full_name', 'phone'], 'parts' => ['product_name', 'sku']], 5,
                fn (Repair $r) => [$r->code, $r->customer?->full_name, $r->contact_phone, $r->customer?->phone,
                    $r->device_name, $r->imei, $r->serial, $r->repair_request,
                    ...$r->parts->pluck('product_name')->all()],
                fn (Repair $r) => $this->result('Phiếu sửa chữa', 'repair', $r->id, $r->code ?: $r->device_name,
                    $this->summary(
                        $r->customer?->full_name ? 'Khách: ' . $r->customer->full_name : null,
                        $r->contact_phone ?: $r->customer?->phone,
                        $r->device_name,
                        $this->matchedItems($r->parts->pluck('product_name')->all(), $tokens, 'Linh kiện'),
                    ),
                    route('repairs.index', ['open_repair' => $r->id], false)),
                true,
            ));
        }

        if ($user->can('categories.view')) {
            $results = $results->concat($this->search(
                Category::query(), $tokens, ['name'], [], 4,
                fn (Category $c) => [$c->name],
                fn (Category $c) => $this->result('Danh mục', 'category', $c->id, $c->name, 'Danh mục sản phẩm',
                    route('categories.index', ['open_category' => $c->id], false)),
            ));
        }

        if ($user->can('brands.view')) {
            $results = $results->concat($this->search(
                Brand::query()->with('categories:id,name'), $tokens, ['name'], [], 4,
                fn (Brand $b) => [$b->name],
                fn (Brand $b) => $this->result('Thương hiệu', 'brand', $b->id, $b->name,
                    $b->categories->pluck('name')->join(', ') ?: null,
                    route('brands.index', ['open_brand' => $b->id], false)),
            ));
        }

        if ($user->can('settings.view')) {
            $results = $results->concat($this->search(
                Unit::query(), $tokens, ['name', 'short_name'], [], 4,
                fn (Unit $u) => [$u->name, $u->short_name],
                fn (Unit $u) => $this->result('Đơn vị tính', 'unit', $u->id, $u->name, $u->short_name,
                    route('settings.index', ['open_unit' => $u->id], false)),
            ));
        }

        if ($user->can('users.view')) {
            $results = $results->concat($this->search(
                User::query(), $tokens, ['name', 'email'], [], 4,
                fn (User $u) => [$u->name, $u->email],
                fn (User $u) => $this->result('Nhân viên', 'user', $u->id, $u->name, $u->email,
                    route('users.index', ['open_user' => $u->id], false)),
            ));
        }

        return response()->json(['results' => $results->take(40)->values()]);
    }

    /**
     * Bước 1: mọi từ khóa đều phải xuất hiện (không phân biệt dấu/hoa thường, không cần đúng thứ tự).
     * Bước 2 (khi chưa đủ kết quả): lấy ứng viên theo mẩu 3 ký tự rồi chấm điểm gần đúng, cho phép gõ sai nhẹ.
     *
     * @param  array<int, string>  $columns
     * @param  array<string, array<int, string>>  $relations  quan hệ => cột
     */
    private function search($query, array $tokens, array $columns, array $relations, int $limit,
        callable $haystack, callable $map, bool $latest = false)
    {
        $order = fn ($q) => $latest ? $q->latest('id') : $q;
        $matchPiece = function ($q, string $piece) use ($columns, $relations) {
            // "d" cũng phải khớp "đ" vì collation không coi hai chữ này là một
            $variants = array_unique([$piece, str_replace('d', 'đ', $piece)]);
            $likes = array_map(fn ($v) => '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v) . '%', $variants);
            $q->where(function ($inner) use ($columns, $relations, $likes) {
                foreach ($likes as $like) {
                    foreach ($columns as $column) {
                        $inner->orWhere($column, 'like', $like);
                    }
                    foreach ($relations as $relation => $relationColumns) {
                        $inner->orWhereHas($relation, function ($related) use ($relationColumns, $like) {
                            $related->where(function ($r) use ($relationColumns, $like) {
                                foreach ($relationColumns as $column) {
                                    $r->orWhere($column, 'like', $like);
                                }
                            });
                        });
                    }
                }
            });
        };

        $exact = $order(clone $query)
            ->where(function ($q) use ($tokens, $matchPiece) {
                foreach ($tokens as $token) {
                    $matchPiece($q, $token);
                }
            })
            ->limit($limit)->get();

        $models = $exact;
        if ($exact->count() < $limit) {
            $candidates = $order(clone $query)
                ->where(function ($q) use ($tokens, $matchPiece) {
                    foreach (FuzzyMatcher::fragments($tokens) as $fragment) {
                        $q->orWhere(fn ($piece) => $matchPiece($piece, $fragment));
                    }
                })
                ->whereNotIn('id', $exact->pluck('id'))
                ->limit(150)->get();

            $fuzzy = $candidates
                ->map(fn ($model) => [$model, FuzzyMatcher::score($tokens, collect($haystack($model))->filter()->join(' '))])
                ->filter(fn ($pair) => $pair[1] !== null)
                ->sortBy(fn ($pair) => $pair[1])
                ->map(fn ($pair) => $pair[0])
                ->take($limit - $exact->count());

            $models = $exact->concat($fuzzy);
        }

        return $models->map($map);
    }

    /** Tên sản phẩm/linh kiện khớp từ khóa, để hiện trong phần tóm tắt. */
    private function matchedItems(array $names, array $tokens, string $label = 'Có'): ?string
    {
        $hits = collect($names)->filter()->unique()
            ->filter(fn ($name) => FuzzyMatcher::score($tokens, $name) !== null)
            ->take(2)->values();

        return $hits->isEmpty() ? null : $label . ': ' . $hits->join(', ');
    }

    private function summary(?string ...$parts): string
    {
        return collect($parts)->filter()->join(' · ');
    }

    private function result(string $type, string $recordType, int $recordId, ?string $label, ?string $detail, string $url, array $meta = []): array
    {
        return [
            'type' => $type,
            'record' => ['type' => $recordType, 'id' => $recordId] + $meta,
            'label' => $label ?: $type,
            'detail' => $detail ?: '',
            'url' => $url,
        ];
    }
}
