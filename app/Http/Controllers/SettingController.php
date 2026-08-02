<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'shop_name' => setting('shop_name'),
                'currency_symbol' => setting('currency_symbol', '₫'),
                'currency_format' => setting('currency_format', 'vi-VN'),
                'allow_negative_stock' => setting('allow_negative_stock', false),
            ]
        ]);
    }

    public function update(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Đã lưu cài đặt');
    }
}
