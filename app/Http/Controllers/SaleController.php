<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    // Danh sách hóa đơn bán hàng
    public function index(): Response
    {
        $sales = Sale::query()
            ->with([
                'customer', // Nạp mối quan hệ khách hàng để hiển thị tên khách
                'user',     // Nạp thông tin thu ngân
                'items.product',
                'items.variant',
                'items.productImei',
                'items.gifts.product',
            ])
            ->when(
                request('search'),
                function ($query) {
                    $search = request('search');
                    $query->where(function ($q) use ($search) {
                        $q->where('code', 'like', '%' . $search . '%')
                          ->orWhereHas('customer', function ($customerQuery) use ($search) {
                              $customerQuery->where('full_name', 'like', '%' . $search . '%')
                                           ->orWhere('phone', 'like', '%' . $search . '%');
                          })
                          ->orWhereHas('items', function ($itemQuery) use ($search) {
                              $itemQuery->whereHas('productImei', function ($imeiQuery) use ($search) {
                                  $imeiQuery->where('imei', 'like', '%' . $search . '%');
                              });
                          });
                    });
                }
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'Sales/Index',
            [
                'sales' => $sales,
                'filters' => [
                    'search' => request('search'),
                ],
            ]
        );
    }

    // Hiển thị chi tiết đơn hàng (Vue Page)
    public function show(Sale $sale): Response
    {
        $sale->load([
            'customer',
            'user',
            'items.product',
            'items.variant',
            'items.productImei',
            'items.gifts.product',
        ]);

        return Inertia::render(
            'Sales/Show',
            [
                'sale' => $sale,
            ]
        );
    }

    // Hiển thị hóa đơn để in (Inertia - Trang in độc lập)
    public function receipt(Sale $sale)
    {
        $sale->load([
            'customer',
            'user',
            'items.product',
            'items.variant',
            'items.productImei',
            'items.gifts.product',
        ]);

        return Inertia::render(
            'Sales/Receipt',
            [
                'sale' => $sale,
            ]
        );
    }
}