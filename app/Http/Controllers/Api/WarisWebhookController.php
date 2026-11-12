<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\WarisQrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarisWebhookController extends Controller
{
    /**
     * Handle incoming webhook notification from WARIS QRIS gateway.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        Log::info('WARIS Webhook Received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        $signatureHeader = $request->header('X-WARIS-Signature')
            ?? $request->header('X-Waris-Signature')
            ?? $request->header('x-waris-signature');

        $configuredSecret = WarisQrisService::getWebhookSecret();

        // 1. Verify HMAC-SHA256 signature if webhook secret is configured
        if (! empty($configuredSecret)) {
            if (! WarisQrisService::verifyWebhookSignature($request->getContent(), $signatureHeader)) {
                Log::warning('WARIS Webhook: Invalid Signature', [
                    'received' => $signatureHeader,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid signature.',
                ], 401);
            }
        }

        $payload = $request->all();
        $event = $payload['event'] ?? 'payment.received';
        $data = $payload['data'] ?? $payload;

        $referenceId = $data['reference_id'] ?? $request->input('reference_id');

        if (empty($referenceId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing reference_id in payload.',
            ], 400);
        }

        // Find transaction by transaction_number
        $transaction = Transaction::where('transaction_number', $referenceId)->first();

        if (! $transaction) {
            Log::warning('WARIS Webhook: Transaction Not Found', [
                'reference_id' => $referenceId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found.',
            ], 404);
        }

        // Only process if status is unpaid or waiting
        if (in_array($transaction->status, ['belum_bayar', 'menunggu'])) {
            DB::transaction(function () use ($transaction, $data, $payload, $referenceId) {
                // Update latest pending payment or create confirmed record
                $payment = TransactionPayment::where('transaction_id', $transaction->id)->latest()->first();

                $oldResp = [];
                if ($payment && ! empty($payment->gateway_response)) {
                    $oldResp = is_array($payment->gateway_response)
                        ? $payment->gateway_response
                        : (json_decode((string) $payment->gateway_response, true) ?? []);
                }

                $paymentMethodName = $data['payment_method'] ?? 'QRIS';
                $bankSender = $data['bank_sender'] ?? null;
                $statusPaid = strtoupper((string) ($data['status'] ?? 'PAID'));

                if ($payment) {
                    $payment->update([
                        'status' => 'confirmed',
                        'gateway_status' => $statusPaid,
                        'gateway_response' => array_merge($oldResp, [
                            'webhook_payload' => $payload,
                            'paid_at' => $data['paid_at'] ?? now()->toIso8601String(),
                            'payment_method' => $paymentMethodName,
                            'bank_sender' => $bankSender,
                        ]),
                        'confirmed_at' => now(),
                    ]);
                }

                // Update Transaction Status to 'diproses' and payment_status to 'lunas'
                $transaction->update([
                    'status' => 'diproses',
                    'payment_status' => 'lunas',
                ]);

                $bankLabel = ! empty($bankSender) ? " ({$bankSender})" : '';
                $transaction->statusHistories()->create([
                    'status' => 'diproses',
                    'description' => "Pembayaran QRIS berhasil diverifikasi via {$paymentMethodName}{$bankLabel}.",
                ]);

                Log::info('WARIS Webhook: Payment Verified Successfully', [
                    'transaction_id' => $transaction->id,
                    'reference_id' => $referenceId,
                    'payment_method' => $paymentMethodName,
                ]);
            });
        }

        return response()->json([
            'status' => 'acknowledged',
            'message' => 'Payment processed successfully.',
        ], 200);
    }
}
