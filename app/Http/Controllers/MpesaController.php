<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use App\Models\Order;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MpesaController extends Controller
{
    private MpesaService $mpesa;

    public function __construct(MpesaService $mpesa)
    {
        $this->mpesa = $mpesa;
    }

    public function stkPush(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'phone' => 'required|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $orderId = (int) $request->input('order_id');
        $phone = $this->mpesa->normalizePhone((string) $request->input('phone'));

        $order = Order::where('user_id', $user->id)->find($orderId);
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'Order already paid',
            ], 409);
        }

        try {
            $res = $this->mpesa->stkPush([
                'amount' => (float) $order->total,
                'phone' => $phone,
                'account_reference' => 'ORDER-' . $order->id,
                'transaction_desc' => 'Order #' . $order->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Failed to initiate payment',
            ], 500);
        }

        $merchantRequestId = (string) ($res['response']['MerchantRequestID'] ?? '');
        $checkoutRequestId = (string) ($res['response']['CheckoutRequestID'] ?? '');

        $txn = MpesaTransaction::create([
            'order_id' => $order->id,
            'phone' => $phone,
            'amount' => $order->total,
            'merchant_request_id' => $merchantRequestId ?: null,
            'checkout_request_id' => $checkoutRequestId ?: null,
            'status' => 'pending',
            'raw_request' => $res['request'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'STK push initiated',
            'data' => [
                'order_id' => $order->id,
                'checkout_request_id' => $txn->checkout_request_id,
                'merchant_request_id' => $txn->merchant_request_id,
            ],
        ], 200);
    }

    public function latestForOrder(Request $request, $orderId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $order = Order::where('user_id', $user->id)->find((int) $orderId);
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $txn = MpesaTransaction::where('order_id', $order->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => $txn,
        ]);
    }

    public function callback(Request $request)
    {
        $secret = (string) (config('mpesa.callback_secret') ?? '');
        if ($secret !== '') {
            $provided = (string) ($request->query('secret') ?? $request->header('X-MPESA-SECRET') ?? '');
            if (!hash_equals($secret, $provided)) {
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => 'Forbidden',
                ], 403);
            }
        }

        $payload = $request->all();
        $stk = $payload['Body']['stkCallback'] ?? null;

        $checkoutRequestId = is_array($stk) ? (string) ($stk['CheckoutRequestID'] ?? '') : '';
        $merchantRequestId = is_array($stk) ? (string) ($stk['MerchantRequestID'] ?? '') : '';
        $resultCode = is_array($stk) ? ($stk['ResultCode'] ?? null) : null;
        $resultDesc = is_array($stk) ? ($stk['ResultDesc'] ?? null) : null;

        $txn = null;
        if ($checkoutRequestId !== '') {
            $txn = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();
        }
        if (!$txn && $merchantRequestId !== '') {
            $txn = MpesaTransaction::where('merchant_request_id', $merchantRequestId)->orderByDesc('id')->first();
        }

        if (!$txn) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted'], 200);
        }

        $receipt = null;
        $amountPaid = null;
        $items = is_array($stk) ? ($stk['CallbackMetadata']['Item'] ?? []) : [];
        if (is_array($items)) {
            foreach ($items as $it) {
                if (is_array($it) && ($it['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $receipt = (string) ($it['Value'] ?? '');
                }
                if (is_array($it) && ($it['Name'] ?? '') === 'Amount') {
                    $amountPaid = is_numeric($it['Value'] ?? null) ? (float) $it['Value'] : null;
                }
            }
        }

        $codeInt = is_numeric($resultCode) ? (int) $resultCode : null;
        $status = $codeInt === 0 ? 'success' : 'failed';

        if ($codeInt === 0 && (!$receipt || $amountPaid === null)) {
            $codeInt = 1;
            $status = 'failed';
            $resultDesc = 'Missing receipt or amount';
        }

        $txn->update([
            'status' => $status,
            'result_code' => $codeInt,
            'result_desc' => is_string($resultDesc) ? $resultDesc : null,
            'mpesa_receipt_number' => $receipt ?: null,
            'raw_callback' => $payload,
        ]);

        if ($codeInt === 0) {
            $order = Order::find($txn->order_id);
            if ($order && $order->payment_status !== 'paid' && $order->payment_method === 'mpesa') {
                $expected = (float) $order->total;
                if ($amountPaid === null || abs($expected - (float) $amountPaid) > 0.01) {
                    $txn->update([
                        'status' => 'failed',
                        'result_code' => 1,
                        'result_desc' => 'Amount mismatch',
                    ]);
                    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted'], 200);
                }

                $order->payment_status = 'paid';
                $order->save();
            }
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted'], 200);
    }
}
