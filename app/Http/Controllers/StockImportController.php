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

        $imports = StockImport::query()
            ->with([
                'supplier:id,name,phone',
                'user:id,name',
            ])
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplier) use ($search) {
                            $supplier
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('StockImports/List', [
            'imports' => $imports,

            'filters' => [
                'search' => $search,
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

    /**
     * Lưu phiếu nhập hàng
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => [
                'required',
                'exists:suppliers,id',
            ],

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

        $this->service->import($request->all());

        return back()->with(
            'success',
            'Nhập hàng thành công'
        );
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
