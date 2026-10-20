<?php

namespace App\Http\Controllers;

use App\Models\BankPaymentIntent;
use App\Models\BankPaymentTransaction;
use App\Models\Sale;
use App\Services\PosCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BankTransferWebhookController extends Controller
{
    public function receive(Request $request, PosCheckoutService $checkout)
    {
        $accounts = json_decode((string) setting('bank_accounts', '[]'), true) ?: [];
        $decrypt = static function (string $value): string {
            if ($value === '') return '';
            try { return Crypt::decryptString($value); } catch (Throwable) { return $value; }
        };
        $payload = $request->json()->all();
        $provider = null;
        $matchedAccount = null;
        $transaction = [];
        $signature = strtolower(trim((string) $request->header('ApiPay-Signature', '')));
        $rawBody = $request->getContent();
        foreach ($accounts as $account) {
            $candidateProvider = strtolower((string) ($account['payment_provider'] ?? ''));
            if ($candidateProvider === 'apipay') {
                $secret = $decrypt((string) ($account['payment_webhook_secret'] ?? ''));
                if ($secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
                    $provider = 'apipay';
                    $matchedAccount = $account;
                    break;
                }
            } elseif ($candidateProvider === 'sepay') {
                $apiKey = $decrypt((string) ($account['payment_api_key'] ?? ''));
                $secret = $decrypt((string) ($account['payment_webhook_secret'] ?? ''));
                $authorization = (string) $request->header('Authorization', '');
                $providedApiKey = preg_match('/^Apikey\s+(.+)$/i', $authorization, $authMatch) ? trim($authMatch[1]) : '';
                $providedSecret = (string) $request->header('X-Webhook-Secret', '');
                if (($apiKey !== '' && $providedApiKey !== '' && hash_equals($apiKey, $providedApiKey))
                    || ($secret !== '' && $providedSecret !== '' && hash_equals($secret, $providedSecret))) {
                    $provider = 'sepay';
                    $matchedAccount = $account;
                    break;
                }
            }
        }
        abort_unless($provider && $matchedAccount, 401, 'Webhook không hợp lệ hoặc chưa khớp cấu hình tài khoản.');

        if ($provider === 'apipay') {
            $payload = $request->json()->all();
            if (! empty($payload['sandbox'])) {
                return response()->json(['received' => true, 'success' => true, 'sandbox' => true]);
            }
            if (($payload['event'] ?? null) !== 'transaction.in') {
                return response()->json(['received' => true, 'matched' => false]);
            }
            $transaction = $payload['data'] ?? [];
            $data = [
                'transaction_id' => $transaction['transactionId'] ?? null,
                'amount' => $transaction['amount'] ?? null,
                'description' => trim(implode(' ', array_filter([
                    $transaction['content'] ?? null,
                    $transaction['referenceCode'] ?? null,
                ], fn ($value) => is_scalar($value) && trim((string) $value) !== ''))),
                'account_number' => (string) ($transaction['accountNumber'] ?? ''),
                'account_public_id' => $transaction['bankAccountPublicId'] ?? '',
                'direction' => 'in',
            ];
        } else {
            // SePay native payload.
            $data = [
                'transaction_id' => $request->input('transaction_id', $request->input('id')),
                'amount' => $request->input('amount', $request->input('transferAmount')),
                'description' => trim(implode(' ', array_filter([
                    $request->input('description'), $request->input('content'), $request->input('code'),
                ], fn ($value) => is_scalar($value) && trim((string) $value) !== ''))),
                'account_number' => (string) $request->input('account_number', $request->input('accountNumber', '')),
                'account_public_id' => '',
                'direction' => $request->input('direction', $request->input('transferType')),
            ];
        }
        if ($data['direction'] !== null && ! in_array($data['direction'], ['in', 'credit', 'received'], true)) {
            return response()->json(['success' => true, 'matched' => false, 'message' => 'Đã bỏ qua giao dịch tiền ra.']);
        }
        $data['transaction_id'] = is_scalar($data['transaction_id']) ? (string) $data['transaction_id'] : $data['transaction_id'];
        $data['account_number'] = is_scalar($data['account_number']) ? (string) $data['account_number'] : $data['account_number'];
        validator($data, [
            'transaction_id' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'direction' => ['required', 'in:in,credit,received'],
        ])->validate();

        if (! preg_match('/INV-[0-9]{14}/', strtoupper((string) ($data['description'] ?? '')), $match)) {
            return response()->json(['received' => true, 'success' => true, 'matched' => false, 'message' => 'Không tìm thấy mã hóa đơn trong nội dung giao dịch.']);
        }

        $intent = BankPaymentIntent::query()->where('invoice_code', $match[0])->first();
        $accountMatches = $intent && $intent->bank_account === (string) ($matchedAccount['bank_account'] ?? '')
            && ($data['account_number'] === '' || $intent->bank_account === (string) $data['account_number']);
        $publicId = (string) ($matchedAccount['payment_provider_account_id'] ?? '');
        $publicAccountMatches = $intent && filled($publicId) && filled($data['account_public_id'])
            && hash_equals($publicId, (string) $data['account_public_id'])
            && $intent->bank_account === (string) ($matchedAccount['bank_account'] ?? '');
        if (! $intent || (! $accountMatches && ! $publicAccountMatches)) {
            return response()->json(['received' => true, 'success' => true, 'matched' => false]);
        }

        try {
            $result = DB::transaction(function () use ($intent, $data, $checkout) {
                $locked = BankPaymentIntent::query()->lockForUpdate()->find($intent->id);
                if (! $locked || $locked->status !== 'waiting' || ($locked->received_amount == 0 && $locked->expires_at->isPast())) {
                    if ($locked?->status === 'waiting' && $locked->received_amount == 0) $locked->update(['status' => 'expired']);
                    return ['matched' => true, 'status' => $locked?->status ?? 'expired'];
                }

                if (BankPaymentTransaction::query()->where('transaction_id', $data['transaction_id'])->exists()) {
                    return ['matched' => true, 'status' => $locked->status, 'duplicate' => true];
                }

                BankPaymentTransaction::query()->create([
                    'transaction_id' => $data['transaction_id'],
                    'bank_payment_intent_id' => $locked->id,
                    'amount' => $data['amount'],
                    'description' => $data['description'],
                ]);

                $received = (int) $locked->received_amount + (int) $data['amount'];
                $locked->update(['received_amount' => $received]);
                if ($received < (int) $locked->expected_amount) {
                    return ['matched' => true, 'status' => 'waiting', 'received_amount' => $received];
                }

                $payload = $locked->checkout_payload;
                $sale = $checkout->checkout(
                    items: $payload['items'],
                    customerId: $payload['customer_id'] ? (int) $payload['customer_id'] : null,
                    paidAmount: $received,
                    paymentMethod: 'bank',
                    payOldDebt: (bool) $payload['pay_old_debt'],
                    userId: (int) $locked->user_id,
                    invoiceCode: $locked->invoice_code,
                );
                $sale->update(['work_shift_id' => $locked->work_shift_id, 'bank_account' => $locked->bank_account]);
                $sale->load(['items.product', 'items.variant', 'items.productImei', 'customer']);
                $saleResponse = $this->saleResponse($sale);
                $locked->update(['status' => 'paid', 'sale_id' => $sale->id, 'sale_response' => $saleResponse]);

                return ['matched' => true, 'status' => 'paid', 'received_amount' => $received];
            });
        } catch (Throwable $exception) {
            Log::error('Bank payment webhook failed.', ['transaction_id' => $data['transaction_id'], 'exception' => $exception]);
            return response()->json(['success' => false, 'message' => 'Không thể ghi nhận giao dịch.'], 500);
        }

        return response()->json(['received' => true, 'success' => true, ...$result]);
    }

    private function saleResponse(Sale $sale): array
    {
        return [
            'success' => true,
            'data' => [
                'id' => $sale->id,
                'code' => $sale->code,
                'customer' => ['full_name' => $sale->customer?->full_name ?? 'Khách lẻ'],
                'items' => $sale->items->map(fn ($item) => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'unit_name' => $item->unit_name,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    'imei' => $item->productImei?->imei,
                    'warranty_days' => $item->warranty_days,
                    'warranty_value' => $item->warranty_value,
                    'warranty_unit' => $item->warranty_unit,
                    'variant' => $item->variant ? ['id' => $item->variant->id, 'attributes' => $item->variant->attributes] : null,
                    'product' => ['name' => $item->product?->name],
                ])->all(),
                'subtotal' => $sale->subtotal,
                'paid' => (float) $sale->paid_amount,
                'change' => (float) $sale->change_amount,
                'payment_method' => 'bank',
            ],
        ];
    }
}
