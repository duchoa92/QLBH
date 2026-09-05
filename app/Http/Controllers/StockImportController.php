<?php

namespace App\Http\Controllers;

use App\Services\Stock\StockImportService;
use Illuminate\Http\Request;

class StockImportController extends Controller
{
    protected $service;

    public function __construct(StockImportService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array'
        ]);

        $this->service->import($request->all());

        return back()->with('success', 'Nhập hàng thành công');
    }
}