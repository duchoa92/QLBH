<?php

namespace App\Services\Lookup;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductImeiHistory;
use App\Models\Repair;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tra cứu các đối tượng ngoài thiết bị: khách hàng, người dùng, nhà cung cấp,
 * hóa đơn bán, phiếu nhập — gợi ý khi gõ và hồ sơ chi tiết khi chọn.
 *
 * Chỉ dùng các cột đã được các màn hình khác đọc/ghi trong hệ thống.
 */
class EntityLookupService
{
    public const TYPES = ['customer', 'user', 'supplier', 'sale', 'import', 'product'];

    /** Quyền xem tương ứng — người dùng không có quyền thì không tra cứu được loại dữ liệu đó. */
    public const PERMISSIONS = [
        'customer' => 'customers.view',
        'user' => 'users.view',
        'supplier' => 'suppliers.view',
        'sale' => 'sales.view',
        'import' => 'stock_imports.view',
        'product' => 'products.view',
    ];

    /** Tách từ khóa thành các từ: mọi từ đều phải xuất hiện (ở bất kỳ trường nào) thì mới khớp. */
    public static function tokens(string $keyword): array
    {
        return collect(preg_split('/\s+/u', trim($keyword)) ?: [])
            ->filter(fn ($token) => $token !== '')
            ->map(fn ($token) => mb_substr($token, 0, 60))
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }

    public static function likeOf(string $value): string
    {
        return '%' . addcslashes($value, '\\%_') . '%';
    }

    /** Chữ thường, bỏ dấu tiếng Việt — khớp với cột search_text của hệ thống. */
    public static function normalized(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }

    private static function phoneLikeOf(string $token): string
    {
        $digits = preg_replace('/\D+/', '', $token) ?? '';

        return strlen($digits) >= 3 ? '%' . $digits . '%' : self::likeOf($token);
    }

    private const SALE_STATUS = [
        'completed' => 'Hoàn tất', 'paid' => 'Đã thanh toán', 'cancelled' => 'Đã hủy', 'pending' => 'Đang chờ',
    ];

    private const IMPORT_STATUS = [
        'completed' => 'Hoàn thành', 'draft' => 'Nháp', 'cancelled' => 'Đã hủy', 'pending' => 'Chờ xử lý',
    ];

    /* ------------------------------------------------------------------
     | Gợi ý
     |------------------------------------------------------------------*/

    public function suggest(string $type, string $keyword, int $limit): Collection
    {
        $tokens = self::tokens($keyword);

        if ($tokens === []) {
            return collect();
        }

        return match ($type) {
            'customer' => $this->tokenQuery(Customer::query(), $tokens, fn ($w, $t) => $w
                    ->where('full_name', 'like', self::likeOf($t))
                    ->orWhere('search_text', 'like', self::likeOf(self::normalized($t)))
                    ->orWhere('code', 'like', self::likeOf($t))
                    ->orWhere('cccd', 'like', self::likeOf($t))
                    ->orWhere('email', 'like', self::likeOf($t))
                    ->orWhere('phone', 'like', self::phoneLikeOf($t)))
                ->orderBy('full_name')->limit($limit)->get()
                ->map(fn (Customer $c) => $this->customerItem($c, $keyword)),

            'user' => $this->tokenQuery(User::query(), $tokens, fn ($w, $t) => $w
                    ->where('name', 'like', self::likeOf($t))
                    ->orWhere('username', 'like', self::likeOf($t))
                    ->orWhere('email', 'like', self::likeOf($t))
                    ->orWhere('phone', 'like', self::phoneLikeOf($t)))
                ->orderBy('name')->limit($limit)->get()
                ->map(fn (User $u) => $this->userItem($u, $keyword)),

            'supplier' => $this->tokenQuery(Supplier::query(), $tokens, fn ($w, $t) => $w
                    ->where('name', 'like', self::likeOf($t))
                    ->orWhere('search_text', 'like', self::likeOf(self::normalized($t)))
                    ->orWhere('code', 'like', self::likeOf($t))
                    ->orWhere('email', 'like', self::likeOf($t))
                    ->orWhere('phone', 'like', self::phoneLikeOf($t)))
                ->orderBy('name')->limit($limit)->get()
                ->map(fn (Supplier $s) => $this->supplierItem($s, $keyword)),

            'sale' => $this->tokenQuery(Sale::query()->with('customer:id,full_name,phone'), $tokens, fn ($w, $t) => $w
                    ->where('code', 'like', self::likeOf($t))
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', self::likeOf($t))
                        ->orWhere('search_text', 'like', self::likeOf(self::normalized($t)))
                        ->orWhere('phone', 'like', self::phoneLikeOf($t))))
                ->latest('id')->limit($limit)->get()
                ->map(fn (Sale $s) => $this->saleItem($s, $keyword)),

            'import' => $this->tokenQuery(StockImport::query()->with('supplier:id,name,phone'), $tokens, fn ($w, $t) => $w
                    ->where('code', 'like', self::likeOf($t))
                    ->orWhere('party_name', 'like', self::likeOf($t))
                    ->orWhere('party_phone', 'like', self::phoneLikeOf($t))
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', self::likeOf($t))
                        ->orWhere('search_text', 'like', self::likeOf(self::normalized($t)))
                        ->orWhere('phone', 'like', self::phoneLikeOf($t))))
                ->latest('id')->limit($limit)->get()
                ->map(fn (StockImport $i) => $this->importItem($i, $keyword)),

            'product' => $this->suggestProducts($keyword, $limit),

            default => collect(),
        };
    }

    /**
     * Sản phẩm trong danh mục hàng hóa (kể cả sản phẩm không quản lý theo IMEI như màn hình, linh kiện).
     * $mode: null = tất cả · 'sold' = đã từng bán · 'in_stock' = còn tồn kho.
     * Với 'sold' / 'in_stock' bỏ qua sản phẩm quản lý theo IMEI vì từng thiết bị đã được liệt kê riêng.
     */
    public function suggestProducts(string $keyword, int $limit, ?string $mode = null): Collection
    {
        $tokens = self::tokens($keyword);

        if ($tokens === []) {
            return collect();
        }

        $query = Product::query()->with(['category:id,name', 'brand:id,name']);

        $this->tokenQuery($query, $tokens, fn ($w, $t) => $w
            ->where('name', 'like', self::likeOf($t))
            ->orWhere('search_text', 'like', self::likeOf(self::normalized($t)))
            ->orWhere('sku', 'like', self::likeOf($t))
            ->orWhere('model', 'like', self::likeOf($t))
            ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', self::likeOf($t))
                ->orWhere('barcode', 'like', self::likeOf($t))
                ->orWhere('attributes', 'like', self::likeOf($t))));

        if ($mode === 'in_stock') {
            $query->where('stock', '>', 0)->where('manage_stock_by_serial', false);
        } elseif ($mode === 'sold') {
            $query->where('manage_stock_by_serial', false)
                ->whereIn('id', fn ($sub) => $sub->select('product_id')->from('sale_items')->whereNotNull('product_id'));
        }

        return $query->orderBy('name')->limit($limit)->get()
            ->map(fn (Product $p) => $this->productItem($p, $keyword));
    }

    /** Áp từng từ khóa: mọi từ đều phải khớp ít nhất một trường. */
    private function tokenQuery($query, array $tokens, \Closure $match)
    {
        foreach ($tokens as $token) {
            $query->where(fn ($w) => $match($w, $token));
        }

        return $query;
    }

    /* ------------------------------------------------------------------
     | Hồ sơ chi tiết
     |------------------------------------------------------------------*/

    public function profile(string $type, int $id, ?string $deviceStatus = null): ?array
    {
        return match ($type) {
            'customer' => $this->customerProfile($id),
            'user' => $this->userProfile($id),
            'supplier' => $this->supplierProfile($id),
            'sale' => $this->saleProfile($id),
            'import' => $this->importProfile($id),
            'product' => $this->productProfile($id, $deviceStatus),
            default => null,
        };
    }

    private function customerProfile(int $id): ?array
    {
        $customer = Customer::query()->find($id);

        if (! $customer) {
            return null;
        }

        $devices = SaleItem::query()
            ->with(['productImei.product:id,name,sku', 'productImei.variant:id,product_id,sku,attributes'])
            ->whereNotNull('product_imei_id')
            ->whereHas('sale', fn ($sale) => $sale->where('customer_id', $customer->id))
            ->latest('id')
            ->limit(50)
            ->get()
            ->pluck('productImei')
            ->filter()
            ->unique('id')
            ->map(fn (ProductImei $imei) => $this->deviceItem($imei));

        $repairs = Repair::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (Repair $r) => $this->repairItem($r));

        $sales = Sale::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Sale $s) => $this->saleItem($s->setRelation('customer', $customer)));

        return $this->entity('customer', 'Khách hàng', $customer->full_name ?: 'Khách hàng', $customer->code, [
            ['label' => 'Số điện thoại', 'value' => $customer->phone],
            ['label' => 'CCCD', 'value' => $customer->cccd],
            ['label' => 'Địa chỉ', 'value' => $customer->address],
            ['label' => 'Công nợ', 'value' => $this->money($customer->debt_balance)],
            ['label' => 'Tổng chi tiêu', 'value' => $this->money($customer->total_spent)],
            ['label' => 'Số đơn đã mua', 'value' => $customer->total_orders],
        ], [
            ['title' => 'Thiết bị đã mua', 'items' => $devices],
            ['title' => 'Phiếu sửa chữa', 'items' => $repairs],
            ['title' => 'Hóa đơn gần đây', 'items' => $sales],
        ]);
    }

    private function userProfile(int $id): ?array
    {
        $user = User::query()->find($id);

        if (! $user) {
            return null;
        }

        $sales = Sale::query()->with('customer:id,full_name,phone')->where('user_id', $user->id)->latest('id')->limit(20)->get()
            ->map(fn (Sale $s) => $this->saleItem($s));
        $repairs = Repair::query()->where('technician_id', $user->id)->latest('id')->limit(20)->get()
            ->map(fn (Repair $r) => $this->repairItem($r));
        $imports = StockImport::query()->with('supplier:id,name,phone')->where('user_id', $user->id)->latest('id')->limit(20)->get()
            ->map(fn (StockImport $i) => $this->importItem($i));

        return $this->entity('user', 'Người dùng', $user->name ?: $user->username, $user->username, [
            ['label' => 'Tên đăng nhập', 'value' => $user->username],
            ['label' => 'Số điện thoại', 'value' => $user->phone],
        ], [
            ['title' => 'Hóa đơn đã lập', 'items' => $sales],
            ['title' => 'Phiếu sửa phụ trách', 'items' => $repairs],
            ['title' => 'Phiếu nhập đã tạo', 'items' => $imports],
        ]);
    }

    private function supplierProfile(int $id): ?array
    {
        $supplier = Supplier::query()->find($id);

        if (! $supplier) {
            return null;
        }

        $imports = StockImport::query()->with('supplier:id,name,phone')->where('supplier_id', $supplier->id)->latest('id')->limit(30)->get()
            ->map(fn (StockImport $i) => $this->importItem($i));

        $importIds = StockImport::query()->where('supplier_id', $supplier->id)->pluck('id');
        $codes = $importIds->isEmpty()
            ? collect()
            : ProductImeiHistory::query()
                ->where('type', 'import')
                ->whereIn('reference_id', $importIds)
                ->latest('id')
                ->limit(60)
                ->pluck('imei')
                ->unique()
                ->values();

        $devices = $codes->isEmpty()
            ? collect()
            : ProductImei::query()
                ->with(['product:id,name,sku', 'variant:id,product_id,sku,attributes'])
                ->whereIn('imei', $codes)
                ->limit(50)
                ->get()
                ->map(fn (ProductImei $imei) => $this->deviceItem($imei));

        return $this->entity('supplier', 'Nhà cung cấp', $supplier->name, $supplier->code, [
            ['label' => 'Số điện thoại', 'value' => $supplier->phone],
            ['label' => 'Email', 'value' => $supplier->email],
            ['label' => 'Địa chỉ', 'value' => $supplier->address],
            ['label' => 'Công nợ phải trả', 'value' => $this->money($supplier->debt_balance)],
        ], [
            ['title' => 'Phiếu nhập gần đây', 'items' => $imports],
            ['title' => 'Thiết bị đã nhập từ nhà cung cấp', 'items' => $devices],
        ]);
    }


    private function productProfile(int $id, ?string $deviceStatus = null): ?array
    {
        $product = Product::query()
            ->with(['category:id,name', 'brand:id,name', 'unit:id,name', 'variants'])
            ->find($id);

        if (! $product) {
            return null;
        }

        $devices = ProductImei::query()
            ->with(['product:id,name,sku', 'variant:id,product_id,sku,attributes'])
            ->where('product_id', $product->id)
            ->when($deviceStatus, fn ($query) => $query->where('status', $deviceStatus))
            ->latest('id')
            ->limit(60)
            ->get()
            ->map(fn (ProductImei $imei) => $this->deviceItem($imei));

        $variants = $product->variants->map(fn ($variant) => [
            'type' => 'line',
            'title' => $this->variantLabel($variant) ?: ($variant->sku ?: 'Biến thể'),
            'lines' => array_values(array_filter([
                $variant->sku ? 'SKU ' . $variant->sku : null,
                'Tồn: ' . (float) $variant->stock,
                'Giá nhập ' . $this->money($variant->cost_price),
                'Giá bán ' . $this->money($variant->sell_price),
            ])),
        ])->values();

        $batches = $product->stockBatches()
            ->where('remaining_quantity', '>', 0)
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn ($batch) => [
                'type' => 'line',
                'title' => 'Lô nhập ' . ($batch->imported_at ? \Illuminate\Support\Carbon::parse($batch->imported_at)->format('d/m/Y') : ''),
                'lines' => [
                    'Còn: ' . (float) $batch->remaining_quantity,
                    'Giá nhập ' . $this->money($batch->cost_price),
                    'Giá bán ' . $this->money($batch->sell_price),
                ],
            ])->values();

        return $this->entity('product', 'Sản phẩm', $product->name, $product->sku, [
            ['label' => 'Model', 'value' => $product->model],
            ['label' => 'Danh mục', 'value' => $product->category?->name],
            ['label' => 'Thương hiệu', 'value' => $product->brand?->name],
            ['label' => 'Đơn vị', 'value' => $product->unit?->name],
            ['label' => 'Giá nhập', 'value' => $this->money($product->cost_price)],
            ['label' => 'Giá bán', 'value' => $this->money($product->sell_price)],
            ['label' => 'Tồn kho', 'value' => (float) $product->stock],
            ['label' => 'Bảo hành', 'value' => $product->warranty_days ? $product->warranty_days . ' ngày' : null],
            ['label' => 'Quản lý theo IMEI', 'value' => $product->manage_stock_by_serial ? 'Có' : 'Không'],
        ], [
            ['title' => match ($deviceStatus) {
                ProductImei::STATUS_SOLD => 'Thiết bị đã bán',
                ProductImei::STATUS_IN_STOCK => 'Thiết bị trong kho',
                default => 'Thiết bị IMEI / Serial',
            }, 'items' => $devices],
            ['title' => 'Biến thể', 'items' => $variants],
            ['title' => 'Lô hàng còn tồn', 'items' => $batches],
        ]);
    }

    private function saleProfile(int $id): ?array
    {
        $sale = Sale::query()->with(['customer:id,full_name,phone', 'user:id,name'])->find($id);

        if (! $sale) {
            return null;
        }

        $items = SaleItem::query()
            ->with(['product:id,name,sku', 'productImei.product:id,name,sku', 'productImei.variant:id,product_id,sku,attributes'])
            ->where('sale_id', $sale->id)
            ->get();

        $devices = $items->pluck('productImei')->filter()->unique('id')
            ->map(fn (ProductImei $imei) => $this->deviceItem($imei));

        $lines = $items->whereNull('product_imei_id')->map(fn (SaleItem $item) => [
            'type' => 'line',
            'title' => $item->product?->name ?: 'Sản phẩm',
            'lines' => array_values(array_filter([
                 'SL: ' . (float) $item->quantity . ($item->unit_name ? ' ' . $item->unit_name : ''),
                 'Thành tiền: ' . $this->money($item->subtotal),
            ])),
        ])->values();

        return $this->entity('sale', 'Hóa đơn bán', $sale->code, $sale->created_at?->format('d/m/Y H:i'), [
            ['label' => 'Khách hàng', 'value' => $sale->customer?->full_name ?: 'Khách lẻ'],
            ['label' => 'Số điện thoại', 'value' => $sale->customer?->phone],
            ['label' => 'Nhân viên', 'value' => $sale->user?->name],
            ['label' => 'Tổng tiền', 'value' => $this->money($sale->grand_total)],
            ['label' => 'Đã thanh toán', 'value' => $this->money($sale->paid_amount)],
            ['label' => 'Trạng thái', 'value' => self::SALE_STATUS[strtolower((string) $sale->status)] ?? $sale->status],
        ], [
            ['title' => 'Thiết bị trong hóa đơn', 'items' => $devices],
            ['title' => 'Sản phẩm khác', 'items' => $lines],
        ]);
    }

    private function importProfile(int $id): ?array
    {
        $import = StockImport::query()
            ->with(['supplier:id,name,phone', 'user:id,name', 'items.product:id,name,sku'])
            ->find($id);

        if (! $import) {
            return null;
        }

        $codes = ProductImeiHistory::query()
            ->where('type', 'import')
            ->where('reference_id', $import->id)
            ->pluck('imei')
            ->unique()
            ->values();

        $devices = $codes->isEmpty()
            ? collect()
            : ProductImei::query()
                ->with(['product:id,name,sku', 'variant:id,product_id,sku,attributes'])
                ->whereIn('imei', $codes)
                ->limit(100)
                ->get()
                ->map(fn (ProductImei $imei) => $this->deviceItem($imei));

        $lines = $import->items->map(fn ($item) => [
            'type' => 'line',
            'title' => $item->product?->name ?: 'Sản phẩm',
            'lines' => [
                'SL: ' . (float) $item->quantity,
                'Giá nhập: ' . $this->money($item->cost_price),
            ],
        ])->values();

        $partyName = $import->party_type === 'customer'
            ? ($import->party_name ?: 'Khách lẻ')
            : ($import->supplier?->name ?: $import->party_name);

        return $this->entity('import', 'Phiếu nhập', $import->code, $import->import_date ? (string) $import->import_date : null, [
            ['label' => $import->party_type === 'customer' ? 'Mua từ khách' : 'Nhà cung cấp', 'value' => $partyName],
            ['label' => 'Số điện thoại', 'value' => $import->supplier?->phone ?: $import->party_phone],
            ['label' => 'Người tạo', 'value' => $import->user?->name],
            ['label' => 'Tổng tiền', 'value' => $this->money($import->grand_total)],
            ['label' => 'Đã thanh toán', 'value' => $this->money($import->paid_amount)],
            ['label' => 'Trạng thái', 'value' => self::IMPORT_STATUS[strtolower((string) $import->status)] ?? $import->status],
        ], [
            ['title' => 'Thiết bị IMEI đã nhập', 'items' => $devices],
            ['title' => 'Các dòng hàng', 'items' => $lines],
        ]);
    }

    /* ------------------------------------------------------------------
     | Dựng item hiển thị
     |------------------------------------------------------------------*/

    public function deviceItem(ProductImei $imei): array
    {
        return [
            'type' => 'imei',
            'id' => $imei->id,
            'imei' => $imei->imei,
            'serial' => $imei->serial,
            'product_name' => $imei->product?->name,
            'sku' => $imei->product?->sku,
            'variant' => $this->variantLabel($imei->variant),
            'status' => $imei->status,
            'customer_name' => null,
            'customer_phone' => null,
            'sale_code' => null,
            'match' => '',
        ];
    }

    public function repairItem(Repair $repair): array
    {
        return [
            'type' => 'repair',
            'repair_id' => $repair->id,
            'code' => $repair->code,
            'device_name' => $repair->device_name,
            'imei' => $repair->imei,
            'serial' => $repair->serial,
            'status' => $repair->status,
            'customer_name' => null,
            'customer_phone' => $repair->contact_phone,
            'match' => '',
        ];
    }

    private function productItem(Product $p, string $keyword = ''): array
    {
        return $this->generic('product', $p->id, $p->name, array_filter([
            $p->sku,
            $p->brand?->name,
            'Tồn: ' . (float) $p->stock,
            $this->money($p->sell_price),
        ]), 'Sản phẩm', 'indigo', $keyword, [
            'Tên sản phẩm' => [$p->name],
            'SKU / Model' => [$p->sku, $p->model],
            'Thương hiệu' => [$p->brand?->name],
        ]);
    }

    private function customerItem(Customer $c, string $keyword = ''): array
    {
        return $this->generic('customer', $c->id, $c->full_name ?: 'Khách hàng', array_filter([
            $c->code,
            $c->phone,
            (float) $c->debt_balance > 0 ? 'Công nợ ' . $this->money($c->debt_balance) : null,
        ]), 'Khách hàng', 'blue', $keyword, [
            'Tên khách' => [$c->full_name],
            'Số điện thoại' => [$c->phone],
            'Mã khách' => [$c->code],
            'CCCD' => [$c->cccd],
        ]);
    }

    private function userItem(User $u, string $keyword = ''): array
    {
        return $this->generic('user', $u->id, $u->name ?: $u->username, array_filter([$u->username, $u->phone]), 'Người dùng', 'slate', $keyword, [
            'Tên' => [$u->name],
            'Tên đăng nhập' => [$u->username],
            'Số điện thoại' => [$u->phone],
        ]);
    }

    private function supplierItem(Supplier $s, string $keyword = ''): array
    {
        return $this->generic('supplier', $s->id, $s->name, array_filter([$s->code, $s->phone, $s->email]), 'Nhà cung cấp', 'amber', $keyword, [
            'Tên nhà cung cấp' => [$s->name],
            'Mã' => [$s->code],
            'Số điện thoại' => [$s->phone],
            'Email' => [$s->email],
        ]);
    }

    private function saleItem(Sale $s, string $keyword = ''): array
    {
        return $this->generic('sale', $s->id, $s->code, array_filter([
            $s->customer?->full_name ?: 'Khách lẻ',
            $s->customer?->phone,
            $this->money($s->grand_total),
        ]), 'Hóa đơn', 'emerald', $keyword, [
            'Mã hóa đơn' => [$s->code],
            'Khách hàng' => [$s->customer?->full_name, $s->customer?->phone],
        ], ['date' => $s->created_at?->format('d/m/Y H:i'), 'date_label' => 'Ngày bán']);
    }

    private function importItem(StockImport $i, string $keyword = ''): array
    {
        $party = $i->party_type === 'customer' ? ($i->party_name ?: 'Khách lẻ') : ($i->supplier?->name ?: $i->party_name);

        return $this->generic('import', $i->id, $i->code, array_filter([
            $party,
            $i->supplier?->phone ?: $i->party_phone,
            $this->money($i->grand_total),
        ]), 'Phiếu nhập', 'purple', $keyword, [
            'Mã phiếu nhập' => [$i->code],
            'Nhà cung cấp / khách' => [$i->supplier?->name, $i->party_name, $i->supplier?->phone, $i->party_phone],
        ], ['date' => $i->import_date ? Carbon::parse($i->import_date)->format('d/m/Y') : null, 'date_label' => 'Ngày nhập']);
    }

    private function generic(string $type, int $id, ?string $title, array $lines, string $badge, string $tone, string $keyword, array $groups, array $extra = []): array
    {
        return $extra + [
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'lines' => array_values($lines),
            'badge' => $badge,
            'tone' => $tone,
            'match' => $keyword === '' ? '' : $this->matchLabel($keyword, $groups),
        ];
    }

    private function entity(string $type, string $label, ?string $title, ?string $subtitle, array $fields, array $sections): array
    {
        return [
            'kind' => 'entity',
            'exists' => true,
            'entity_type' => $type,
            'entity_label' => $label,
            'title' => $title,
            'subtitle' => $subtitle,
            'message' => "Đã tìm thấy {$label} trong hệ thống.",
            'fields' => collect($fields)
                ->filter(fn ($f) => $f['value'] !== null && $f['value'] !== '')
                ->map(fn ($f) => ['label' => $f['label'], 'value' => (string) $f['value']])
                ->values(),
            'sections' => collect($sections)
                ->map(fn ($s) => ['title' => $s['title'], 'items' => collect($s['items'])->values()])
                ->values(),
        ];
    }

    private function money($value): string
    {
        return number_format((float) $value, 0, ',', '.') . ' đ';
    }

    private function variantLabel($variant): ?string
    {
        if (! $variant) {
            return null;
        }

        $attributes = $variant->attributes;

        if (is_array($attributes) && $attributes !== []) {
            $text = collect($attributes)
                ->map(fn ($value) => is_array($value) ? ($value['value'] ?? $value['name'] ?? $value['label'] ?? '') : $value)
                ->filter()
                ->implode(' / ');

            if ($text !== '') {
                return $text;
            }
        }

        return $variant->sku;
    }

    /** Nhãn cho biết gợi ý khớp vì thông tin nào (không phân biệt hoa thường và dấu tiếng Việt). */
    private function matchLabel(string $keyword, array $groups): string
    {
        $needle = Str::lower(Str::ascii($keyword));
        $digits = preg_replace('/\D+/', '', $keyword) ?? '';

        foreach ($groups as $label => $values) {
            foreach ($values as $value) {
                $value = (string) $value;

                if ($value === '') {
                    continue;
                }

                if (str_contains(Str::lower(Str::ascii($value)), $needle)) {
                    return $label;
                }

                if (strlen($digits) >= 3 && str_contains(preg_replace('/\D+/', '', $value) ?? '', $digits)) {
                    return $label;
                }
            }
        }

        return 'Thông tin liên quan';
    }
}
