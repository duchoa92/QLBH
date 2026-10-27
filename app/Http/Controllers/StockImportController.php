<?php

namespace App\Http\Controllers;

use App\Models\StockImport;
use App\Models\ProductImeiHistory;
use App\Services\Stock\StockImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class StockImportController extends Controller
{
    protected StockImportService $service;

    public function __construct(StockImportService $service)
    {
        $this->service = $service;
    }

    /**
     * Danh sách phiếu nhập hàng
     */
    public function index(Request $request): Response
    {
        $search = $request
            ->string('search')
            ->trim()
            ->toString();
        $partyType = in_array($request->query('party_type'), ['supplier', 'customer'], true)
            ? $request->query('party_type')
            : '';
        $status = in_array($request->query('status'), ['completed', 'draft', 'cancelled'], true)
            ? $request->query('status')
            : '';
        $dateFrom = $request->date('date_from')?->toDateString() ?? '';
        $dateTo = $request->date('date_to')?->toDateString() ?? '';
        $perPage = in_array((int) $request->query('per_page', 20), [10, 20, 50, 100], true)
            ? (int) $request->query('per_page', 20) : 20;
        $sortBy = in_array($request->query('sort_by'), ['code', 'import_date', 'items_count', 'grand_total', 'status'], true)
            ? $request->query('sort_by') : 'id';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';

        $imports = StockImport::query()
            ->with([
                'supplier:id,name,phone',
                'customer:id,code,full_name,phone,cccd,address',
                'user:id,name',
            ])
            ->withCount('items')
            ->when($partyType !== '', fn ($query) => $query->where('party_type', $partyType))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('import_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('import_date', '<=', $dateTo))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplier) use ($search) {
                            $supplier
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('customer', function ($customer) use ($search) {
                            $customer
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('cccd', 'like', "%{$search}%");
                        })
                        ->orWhere('party_name', 'like', "%{$search}%")
                        ->orWhere('party_phone', 'like', "%{$search}%");
                });
            })
            ->when($sortBy === 'items_count', fn ($query) => $query->orderBy('items_count', $sortOrder), fn ($query) => $query->orderBy($sortBy, $sortOrder))
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('StockImports/List', [
            'imports' => $imports,

            'filters' => [
                'search' => $search,
                'party_type' => $partyType,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],

            'categories' => Category::query()
                ->orderBy('name')
                ->get(),

            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** Active products with stock normalized across standard, variant, and IMEI inventory. */
    public function replenishmentProducts(): JsonResponse
    {
        $alertsEnabled = filter_var(setting('inventory_alert_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $defaultThreshold = max(0, (int) setting('inventory_alert_threshold', 5));
        $products = Product::query()
            ->where('is_active', true)
            ->select('id', 'category_id', 'name', 'sku', 'barcode', 'stock', 'alert_stock', 'cost_price', 'unit_id', 'product_type', 'manage_stock_by_serial')
            ->with('category:id,name')
            ->with('unit:id,name,short_name')
            ->with(['variants' => fn ($query) => $query
                ->where('is_active', true)
                ->select('id', 'product_id', 'stock')])
            ->withCount(['imeis as in_stock_imeis_count' => fn ($query) =>
                $query->where('status', \App\Models\ProductImei::STATUS_IN_STOCK)])
            ->orderBy('stock')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($alertsEnabled, $defaultThreshold) {
                $stock = $product->manage_stock_by_serial || $product->product_type === 'imei'
                    ? (int) $product->in_stock_imeis_count
                    : ($product->variants->isNotEmpty()
                        ? (float) $product->variants->sum('stock')
                        : (float) $product->stock);

                $productThreshold = (float) $product->alert_stock > 0
                    ? (float) $product->alert_stock
                    : $defaultThreshold;

                return [
                    'id' => $product->id,
                    'category_id' => $product->category_id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'stock' => $stock,
                    'alert_stock' => $alertsEnabled ? $productThreshold : 0,
                    'cost_price' => (float) $product->cost_price,
                    'unit_name' => $product->unit?->short_name ?: ($product->unit?->name ?: 'Cái'),
                ];
            });

        return response()->json([
            'products' => $products,
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'alerts_enabled' => $alertsEnabled,
            'default_alert_threshold' => $defaultThreshold,
        ]);
    }

    /**
     * Lưu phiếu nhập hàng
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'party_type' => ['required', Rule::in(['supplier', 'customer'])],
            'supplier_id' => ['nullable', 'required_if:party_type,supplier', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'party_name' => ['nullable', 'required_if:party_type,customer', 'string', 'max:255'],
            'party_phone' => ['nullable', 'string', 'max:30'],
            'party_address' => ['nullable', 'string', 'max:2000'],
            'party_cccd' => ['nullable', 'string', 'max:30'],

            'import_date' => [
                'required',
                'date',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'exists:products,id',
            ],

            'items.*.variant_id' => [
                'nullable',
                Rule::exists('product_variants', 'id')->where('is_active', true),
            ],

            'items.*.unit_id' => [
                'nullable',
                'exists:units,id',
            ],

            'items.*.unit_name' => [
                'nullable',
                'string',
                'max:50',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.cost_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.sell_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.has_vat' => [
                'nullable',
                'boolean',
            ],

            'items.*.supplier_warranty_value' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'items.*.supplier_warranty_unit' => [
                'nullable',
                'string',
                Rule::in(['days', 'months']),
            ],

            'items.*.extra_info' => ['nullable', 'array'],
            'items.*.extra_info.is_used' => ['nullable', 'boolean'],
            'items.*.extra_info.account' => ['nullable', 'string', 'max:255'],
            'items.*.extra_info.password' => ['nullable', 'string', 'max:255'],

            'items.*.imeis' => [
                'nullable',
                'array',
            ],

            'items.*.imeis.*.imei' => [
                'nullable',
                'string',
                'max:100',
            ],

            'items.*.imeis.*.extra_info' => [
                'nullable',
                'array',
            ],

            'items.*.imeis.*.extra_info.note' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'items.*.imeis.*.extra_info.is_used' => ['nullable', 'boolean'],
            'items.*.imeis.*.extra_info.account' => ['nullable', 'string', 'max:255'],
            'items.*.imeis.*.extra_info.password' => ['nullable', 'string', 'max:255'],

            'items.*.imeis.*.info_image' => [
                'nullable',
                'image',
                'max:5120',
            ],

            'items.*.imeis.*.warranty_duration_value' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'items.*.imeis.*.warranty_duration_unit' => [
                'nullable',
                'string',
                Rule::in(['days', 'months']),
            ],

            'discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'extra_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'note' => [
                'nullable',
                'string',
            ],
        ]);

        if (($validated['party_type'] ?? 'supplier') === 'customer' && !empty($validated['customer_id'])) {
            $customer = Customer::query()->findOrFail($validated['customer_id']);
            $validated['party_name'] = $customer->full_name;
            $validated['party_phone'] = $customer->phone;
            $validated['party_address'] = $customer->address;
            $validated['party_cccd'] = $customer->cccd;
        } elseif (($validated['party_type'] ?? 'supplier') === 'supplier' && !empty($validated['supplier_id'])) {
            $supplier = Supplier::query()->findOrFail($validated['supplier_id']);
            $validated['party_name'] = $supplier->name;
            $validated['party_phone'] = $supplier->phone;
            $validated['party_address'] = $supplier->address;
            $validated['party_cccd'] = null;
        }

        $this->service->import($validated);

        return back()->with(
            'success',
            'Nhập hàng thành công'
        );
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) < 2) {
            return response()->json(['customers' => []]);
        }

        $customers = Customer::query()
            ->where(function ($query) use ($search): void {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('cccd', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('full_name')
            ->limit(10)
            ->get(['id', 'code', 'full_name', 'phone', 'cccd', 'address']);

        return response()->json(['customers' => $customers]);
    }

    public function contract(StockImport $stockImport)
    {
        $stockImport->load([
            'supplier:id,name,phone,email,address,tax_code',
            'customer:id,code,full_name,phone,cccd,address',
            'user:id,name',
            'items.product:id,name,sku,unit_id,conversion_unit_id',
            'items.product.unit:id,name,short_name',
            'items.product.conversionUnit:id,name,short_name',
            'items.variant:id,product_id,sku,attributes',
            'items.unit:id,name,short_name',
        ]);

        return view('stock-imports.contract', ['import' => $stockImport]);
    }

    /**
     * Xem chi tiết phiếu nhập
     */
    public function show(
        Request $request,
        StockImport $stockImport
    ): Response|JsonResponse {
        $stockImport->load([
            'supplier:id,name,phone,email,address',
            'customer:id,code,full_name,phone,cccd,address',
            'user:id,name',
            'items.product:id,name,sku,image,unit_id,conversion_unit_id',
            'items.product.unit:id,name,short_name',
            'items.product.conversionUnit:id,name,short_name',
            'items.variant:id,product_id,sku,attributes,stock',
            'items.unit:id,name,short_name',
        ]);

        $imeiHistories = ProductImeiHistory::query()
            ->with([
                'productImei:id,product_id,variant_id,imei,serial,extra_info,warranty_expired_at,status,cost_price,sell_price',
                'productImei.product:id,name,sku',
                'productImei.variant:id,product_id,sku,attributes',
            ])
            ->where('reference_type', StockImport::class)
            ->where('reference_id', $stockImport->id)
            ->where('type', 'import')
            ->latest('id')
            ->get();

        $stockImport->items->each(function ($item) use ($imeiHistories): void {
            $item->setRelation(
                'import_imeis',
                $imeiHistories
                    ->filter(function (ProductImeiHistory $history) use ($item): bool {
                        return (int) ($history->meta['product_id'] ?? 0) === (int) $item->product_id
                            && (int) ($history->meta['variant_id'] ?? 0) === (int) ($item->variant_id ?? 0);
                    })
                    ->values()
            );
        });

        if ($request->wantsJson()) {
            return response()->json($stockImport);
        }

        return Inertia::render('StockImports/Show', [
            'import' => $stockImport,
        ]);
    }
}
