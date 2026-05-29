<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * Handle incoming callback/webhook from Midtrans.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        Log::info('Midtrans Webhook Received', [
            'body' => $request->all(),
        ]);

        $orderId = $request->input('order_id');
        $statusCode = $request->input('status_code');
        $grossAmount = $request->input('gross_amount');
        $signatureKey = $request->input('signature_key');

        if (! $orderId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing order_id',
            ], 400);
        }

        $transaction = Transaction::where('transaction_number', $orderId)->first();

        if (! $transaction) {
            Log::warning('Midtrans Webhook: Transaction Not Found', [
                'order_id' => $orderId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found',
            ], 404);
        }

        $paymentMethod = $transaction->paymentMethod;
        $serverKey = $paymentMethod?->api_key ?: config('app.midtrans.server_key');

        // 1. Verify Midtrans Signature Key
        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if ($signatureKey !== $expectedSignature) {
            Log::warning('Midtrans Webhook: Invalid Signature', [
                'received' => $signatureKey,
                'expected' => $expectedSignature,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Signature',
            ], 401);
        }

        // 2. Parse Midtrans status
        $transactionStatus = $request->input('transaction_status');
        $fraudStatus = $request->input('fraud_status');

        $isSuccess = false;

        if ($transactionStatus === 'capture') {
            if ($fraudStatus === 'accept') {
                $isSuccess = true;
            }
        } elseif ($transactionStatus === 'settlement') {
            $isSuccess = true;
        }

        // Only update if transaction status is unpaid or waiting
        if (in_array($transaction->status, ['belum_bayar', 'menunggu'])) {
            if ($isSuccess) {
                DB::transaction(function () use ($transaction, $request, $transactionStatus, $orderId) {
                    // Update Transaction Payment
                    $payment = TransactionPayment::where('transaction_id', $transaction->id)
                        ->where('status', 'pending')
                        ->first();

                    if ($payment) {
                        $payment->update([
                            'status' => 'confirmed',
                            'gateway_transaction_id' => $request->input('transaction_id'),
                            'gateway_status' => $transactionStatus,
                            'gateway_response' => json_encode($request->all()),
                            'confirmed_at' => now(),
                        ]);
                    }

                    // Update Transaction Status to 'diproses'
                    $transaction->update([
                        'status' => 'diproses',
                    ]);

                    Log::info('Midtrans Webhook Processed Successfully', [
                        'transaction_id' => $transaction->id,
                        'order_id' => $orderId,
                    ]);
                });
            } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                DB::transaction(function () use ($transaction) {
                    $transaction->update([
                        'status' => 'batal',
                        'cancel_reason' => 'Pembayaran otomatis dibatalkan atau kedaluwarsa oleh gateway pembayaran.',
                        'cancelled_at' => now(),
                    ]);

                    $payment = TransactionPayment::where('transaction_id', $transaction->id)->first();
                    if ($payment) {
                        $payment->update([
                            'status' => 'rejected',
                        ]);
                    }

                    Log::info('Midtrans Webhook: Transaction Cancelled/Expired', [
                        'transaction_id' => $transaction->id,
                        'order_id' => $transaction->transaction_number,
                    ]);
                });
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook processed successfully',
        ], 200);
    }
}
