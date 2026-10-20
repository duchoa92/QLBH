<?php

namespace App\Http\Controllers;

use App\Models\ProductImei;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Sale;
use App\Models\WorkShift;
use App\Services\PosCheckoutService;
use App\Models\BankPaymentIntent;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

class PosController extends Controller
{
    public function startBankPayment(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.discount_type' => ['nullable', 'in:percent,amount'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.imei_id' => ['nullable', 'integer', 'exists:product_imeis,id'],
            'items.*.warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'items.*.warranty_value' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'items.*.warranty_unit' => ['nullable', 'in:day,month'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'items.*.gifts' => ['nullable', 'array'],
            'items.*.gifts.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.gifts.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.gifts.*.imei_id' => ['nullable', 'integer', 'exists:product_imeis,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.unit_name' => ['nullable', 'string', 'max:100'],
            'items.*.conversion_factor' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'pay_old_debt' => ['required', 'boolean'],
            'invoice_code' => ['required', 'string', 'regex:/^INV-[0-9]{14}$/'],
            'expected_amount' => ['required', 'integer', 'min:1', 'max:9999999999999'],
            'bank_account' => ['required', 'string', 'max:50'],
        ]);

        $shift = WorkShift::query()->where('user_id', $request->user()->id)->whereNull('closed_at')->latest('opened_at')->first();
        abort_unless($shift, 422, 'Vui lòng mở ca làm trước khi nhận thanh toán.');
        $exists = BankPaymentIntent::query()->where('invoice_code', $data['invoice_code'])->exists();
        abort_if($exists || Sale::query()->where('code', $data['invoice_code'])->exists(), 422, 'Mã hóa đơn đã được sử dụng.');

        $configuredAccounts = json_decode((string) setting('bank_accounts', '[]'), true) ?: [];
        if (! $configuredAccounts && filled(setting('bank_account', ''))) $configuredAccounts = [['bank_account' => setting('bank_account', '')]];
        $bankAccountConfig = collect($configuredAccounts)->first(fn ($account) => (string) ($account['bank_account'] ?? '') === (string) $data['bank_account']);
        abort_unless($bankAccountConfig, 422, 'Tài khoản nhận tiền không nằm trong danh sách cấu hình.');
        $provider = strtolower((string) ($bankAccountConfig['payment_provider'] ?? ''));
        $decryptSetting = function (string $key) use ($bankAccountConfig): string {
            $value = (string) ($bankAccountConfig[$key] ?? '');
            if ($value === '') return '';
            try { return \Illuminate\Support\Facades\Crypt::decryptString($value); } catch (\Throwable) { return $value; }
        };
        $hasSecret = $decryptSetting('payment_webhook_secret') !== '';
        $hasApiKey = $decryptSetting('payment_api_key') !== '';
        $apiAccessKey = $decryptSetting('payment_api_access_key');
        $apiSecretKey = $decryptSetting('payment_api_secret_key');
        $hasApiPayKeys = $apiAccessKey !== '' && $apiSecretKey !== '';
        abort_unless(($provider === 'apipay' && $hasSecret && $hasApiPayKeys && filled($bankAccountConfig['payment_provider_account_id'] ?? null)) || ($provider === 'sepay' && ($hasSecret || $hasApiKey)), 422, 'Tài khoản ngân hàng này chưa cấu hình đầy đủ dịch vụ nhận giao dịch.');

        $subtotal = collect($data['items'])->sum(fn ($item) => (float) $item['price'] * max(1, (int) ($item['quantity'] ?? 1)));
        $discount = collect($data['items'])->sum(function ($item) {
            $line = (float) $item['price'] * max(1, (int) ($item['quantity'] ?? 1));
            return ($item['discount_type'] ?? null) === 'percent'
                ? $line * (float) ($item['discount_value'] ?? 0) / 100
                : (($item['discount_type'] ?? null) === 'amount' ? (float) ($item['discount_value'] ?? 0) : 0);
        });
        $expected = (int) round(max(0, $subtotal - $discount));
        if ($data['pay_old_debt']) {
            $customer = Customer::query()->findOrFail($data['customer_id'] ?? null);
            $expected += (int) round((float) $customer->debt_balance);
        }
        abort_unless($expected === (int) $data['expected_amount'], 422, 'Tổng tiền cần thanh toán đã thay đổi. Vui lòng mở lại QR.');

        $intent = BankPaymentIntent::query()->create([
            'invoice_code' => $data['invoice_code'],
            'user_id' => $request->user()->id,
            'work_shift_id' => $shift->id,
            'bank_account' => $data['bank_account'],
            'expected_amount' => $expected,
            'received_amount' => 0,
            'checkout_payload' => [
                'items' => $data['items'],
                'customer_id' => $data['customer_id'] ?? null,
                'pay_old_debt' => $data['pay_old_debt'],
            ],
            'status' => 'waiting',
            'expires_at' => now()->addMinutes(15),
        ]);

        $providerPayment = [];
        if ($provider === 'apipay') {
            $auth = base64_encode($apiAccessKey . ':' . $apiSecretKey);
            $response = \Illuminate\Support\Facades\Http::timeout(12)
                ->withHeaders(['Authorization' => 'Bearer ' . $auth])
                ->post('https://app.apipay.vn/v1/client/payment-requests', [
                    'bankPublicId' => $bankAccountConfig['payment_provider_account_id'],
                    'amount' => (string) $expected,
                    'content' => $intent->invoice_code,
                ]);
            $providerPayment = $response->json('data') ?? [];
            if (! $response->successful() || ! filled($providerPayment['payUrl'] ?? null)) {
                $intent->delete();
                \Illuminate\Support\Facades\Log::warning('ApiPay payment request failed.', ['status' => $response->status()]);
                abort(502, 'ApiPay không tạo được yêu cầu thanh toán. Kiểm tra API Key và Bank Account Public ID.');
            }
            $intent->update(['checkout_payload' => [...$intent->checkout_payload, 'provider_payment_url' => $providerPayment['payUrl'], 'provider_qr_url' => $providerPayment['qrUrl'] ?? null]]);
        }

        return response()->json(['success' => true, 'data' => ['id' => $intent->id, 'invoice_code' => $intent->invoice_code, 'expected_amount' => $expected, 'expires_at' => $intent->expires_at, 'provider_payment_url' => $providerPayment['payUrl'] ?? null, 'provider_qr_url' => $providerPayment['qrUrl'] ?? null]]);
    }

    public function bankPaymentStatus(Request $request, BankPaymentIntent $intent)
    {
        abort_unless($intent->user_id === $request->user()->id, 404);
        if ($intent->status === 'waiting' && $intent->received_amount == 0 && $intent->expires_at->isPast()) $intent->update(['status' => 'expired']);

        return response()->json(['success' => true, 'data' => [
            'status' => $intent->status,
            'expected_amount' => (int) $intent->expected_amount,
            'received_amount' => (int) $intent->received_amount,
            'difference' => (int) $intent->received_amount - (int) $intent->expected_amount,
            'sale' => $intent->sale_response,
        ]]);
    }

    public function cancelBankPayment(Request $request, BankPaymentIntent $intent)
    {
        abort_unless($intent->user_id === $request->user()->id, 404);
        if ($intent->status === 'waiting' && $intent->received_amount > 0) {
            return response()->json(['success' => false, 'message' => 'Đã nhận một phần tiền. Không thể dừng đối soát; hãy tiếp tục chờ khách chuyển đủ.'], 422);
        }
        if ($intent->status === 'waiting') $intent->update(['status' => 'cancelled']);
        return response()->json(['success' => true]);
    }
    public function index(): Response
    {
        return Inertia::render(
            'Pos/Index'
        );
    }

    public function scanImei(Request $request) 
    {
        $request->validate([
            'imei' => [
                'required',
            ],
        ]);

        $imei = ProductImei::query()
            ->with([
                'product.unit:id,name,short_name',
            ])
            ->where(
                'imei',
                $request->imei
            )
            ->first();

        if (! $imei) {
            return response()->json([
                'message' => 'IMEI không tồn tại',
            ], 404);
        }

        if (
            $imei->status !==
            ProductImei::STATUS_IN_STOCK
        ) {
            return response()->json([
                'message' => 'IMEI không khả dụng',
            ], 422);
        }

        return response()->json([
            'id' => $imei->id,
            'product_id' => $imei->product->id,
            'imei' => $imei->imei,
                'product' => [
                    'id' => $imei->product->id,
                    'name' => $imei->product->name,
                    'sell_price' => $imei->product->sell_price,
                    'product_type' => $imei->product->product_type,
                    'unit_id' => $imei->product->unit_id,
                    'unit_name' => $imei->product->unit?->short_name ?: $imei->product->unit?->name,
                ],
            ]);
    }

    // Thanh toán
    public function checkout(
        Request $request,
        PosCheckoutService $service,
    )
    {
        $workShift = WorkShift::query()
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        if (! $workShift) {
            return response()->json(['success' => false, 'message' => 'Vui lòng mở ca làm trước khi bán hàng.'], 422);
        }

        // 1. THÊM VALIDATE: Kiểm tra dữ liệu đầu vào chặt chẽ
        $request->validate([

            'items' => 'required|array',

            'payment_method' => 'required|string',
            'bank_account' => 'required_if:payment_method,bank|nullable|string|max:50',
            'invoice_code' => ['nullable', 'string', 'regex:/^INV-[0-9]{14}$/', 'unique:sales,code'],

            'items.*.discount_type' => 'nullable|string',

            'items.*.discount_value' => 'nullable|numeric',

            'items.*.variant_id' => 'nullable|integer',
            
            'items.*.imei_id' => 'nullable|integer|exists:product_imeis,id',

            'items.*.warranty_days' => 'nullable|integer|min:0|max:3650',

            'items.*.warranty_value' => 'nullable|integer|min:0|max:3650',

            'items.*.warranty_unit' => 'nullable|in:day,month',

            'items.*.gift_product_id' => 'nullable|integer',

            'items.*.gifts' => 'nullable|array',

            'items.*.gifts.*.id' => 'required|integer|exists:products,id',

            'items.*.gifts.*.quantity' => 'nullable|integer|min:1',

            'items.*.gifts.*.imei_id' => 'nullable|integer|exists:product_imeis,id',

            'items.*.unit_id' => 'nullable|integer|exists:units,id',

            'items.*.unit_name' => 'nullable|string|max:100',

            'items.*.conversion_factor' => 'nullable|integer|min:1',

        ],
        [
            'items.required' => 'Giỏ hàng không được để trống.',
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
        ]);

        try {

            $sale = $service->checkout(
                items: $request->items,
                customerId: $request->customer_id,
                paidAmount: $request->paid_amount ?? 0,
                paymentMethod: $request->payment_method ?? 'cash',
                payOldDebt: (bool) $request->pay_old_debt,
                userId: auth()->id(),
                invoiceCode: $request->input('invoice_code'),
            );

            $sale->update([
                'work_shift_id' => $workShift->id,
                'bank_account' => $request->payment_method === 'bank' ? $request->bank_account : null,
            ]);

            $sale->load([
                'items.product.conversionUnit',
                'items.variant',
                'items.productImei',
                'items.gifts.product',
                'customer',
            ]);

            Notification::send(
                User::query()->get(),
                new SystemNotification(
                    'Đơn hàng mới',
                    'Hóa đơn ' . $sale->code . ' đã được tạo cho ' . ($sale->customer?->full_name ?? 'khách lẻ') . '.',
                    'sale',
                    route('sales.index')
                )
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $sale->id,
                    'code' => $sale->code,
                    'customer' => [
                        'full_name' => $sale->customer?->full_name ?? 'Khách lẻ',
                    ],
                    'items' => $sale->items->map(function ($item) {

                        return [
                            'id' => $item->id,
                            'quantity' => $item->quantity,
                            'unit_name' => $item->unit_name,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                            'imei' => $item->productImei?->imei,
                            'warranty_days' => $item->warranty_days,
                            'warranty_value' => $item->warranty_value,
                            'warranty_unit' => $item->warranty_unit,
                            'warranty_expires_at' => $item->productImei?->customer_warranty_expires_at,
                            'variant' => $item->variant
                                ? [
                                    'id' => $item->variant->id,
                                    'attributes' => $item->variant->attributes,
                                ]
                                : null,
                            'product' => [
                                'name' => $item->product->name,
                            ],
                        ];
                    }),
                    'subtotal' => $sale->subtotal,
                    'discount' => $sale->discount,
                    'paid' => (float) $sale->paid_amount,
                    'change' => (float) $sale->change_amount,
                    'payment_method' => $sale->payment_method ?? 'cash',
                ],
            ]);

        } catch (\Exception $e) {

            \Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // Xem hóa đơn bán hàng
    public function showSale(Sale $sale): Response 
    {
        $sale->load([
            'items.product.conversionUnit',
            'items.variant',
            'items.productImei',
            'items.gifts.product',
            'customer',
            'user'
        ]);

        return Inertia::render(
            'Pos/SaleInvoice',
            [
                'sale' => $sale,
            ]
        );
    }
}
