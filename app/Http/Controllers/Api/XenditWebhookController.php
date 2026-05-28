<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    /**
     * Handle incoming callback/webhook from Xendit.
     */
    public function handleCallback(Request $request)
    {
        Log::info('Xendit Webhook Received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        // 1. Verify Callback Token
        $callbackTokenHeader = $request->header('x-callback-token');

        $externalId = $request->input('external_id');
        $transaction = $externalId ? Transaction::where('transaction_number', $externalId)->first() : null;
        $paymentMethod = $transaction ? $transaction->paymentMethod : null;

        $expectedToken = ($paymentMethod && $paymentMethod->settings && isset($paymentMethod->settings['webhook_token']))
            ? $paymentMethod->settings['webhook_token']
            : (($paymentMethod && $paymentMethod->api_key) ? $paymentMethod->api_key : config('app.xendit.webhook_token'));

        if (! $callbackTokenHeader || $callbackTokenHeader !== $expectedToken) {
            Log::warning('Xendit Webhook Invalid Token', [
                'received' => $callbackTokenHeader,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Callback Token',
            ], 401);
        }

        // 2. Parse Invoice Callback Payload
        $externalId = $request->input('external_id');
        $status = strtoupper($request->input('status', ''));
        $invoiceId = $request->input('id');

        if (! $externalId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing external_id',
            ], 400);
        }

        // 3. Find Transaction and Process Payment
        $transaction = Transaction::where('transaction_number', $externalId)->first();

        if (! $transaction) {
            Log::warning('Xendit Webhook Transaction Not Found', [
                'external_id' => $externalId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found',
            ], 404);
        }

        // Only update if currently unpaid or waiting
        if (in_array($transaction->status, ['belum_bayar', 'menunggu'])) {
            if ($status === 'PAID' || $status === 'SETTLED') {
                DB::transaction(function () use ($transaction, $invoiceId, $status, $request) {
                    // Update Transaction Payment
                    $payment = TransactionPayment::where('transaction_id', $transaction->id)
                        ->where('status', 'pending')
                        ->first();

                    if ($payment) {
                        $payment->update([
                            'status' => 'confirmed',
                            'gateway_transaction_id' => $invoiceId,
                            'gateway_status' => $status,
                            'gateway_response' => json_encode($request->all()),
                            'confirmed_at' => now(),
                        ]);
                    }

                    // Update Transaction Status
                    $transaction->update([
                        'status' => 'diproses',
                    ]);

                    Log::info('Xendit Webhook Processed Successfully', [
                        'transaction_id' => $transaction->id,
                        'invoice_id' => $invoiceId,
                    ]);
                });
            } else {
                Log::info('Xendit Webhook Ignored Status', [
                    'transaction_number' => $externalId,
                    'status' => $status,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook processed successfully',
        ], 200);
    }
}
