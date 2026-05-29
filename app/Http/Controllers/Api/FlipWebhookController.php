<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlipWebhookController extends Controller
{
    /**
     * Handle incoming callback/webhook from Flip.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        Log::info('Flip Webhook Received', [
            'body' => $request->all(),
        ]);

        $token = $request->input('token');
        $expectedToken = config('app.flip.validation_token');

        // 1. Verify Validation Token
        if ($token !== $expectedToken) {
            Log::warning('Flip Webhook: Invalid Validation Token', [
                'received' => $token,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Token',
            ], 401);
        }

        $dataString = $request->input('data');
        if (! $dataString) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing data',
            ], 400);
        }

        $data = json_decode($dataString, true);
        $billId = $data['bill_id'] ?? null;
        $status = $data['status'] ?? '';

        if (! $billId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing bill_id',
            ], 400);
        }

        // Find payment by gateway_transaction_id
        $payment = TransactionPayment::where('gateway_transaction_id', $billId)
            ->where('status', 'pending')
            ->first();

        if (! $payment) {
            Log::warning('Flip Webhook: Payment Record Not Found', [
                'bill_id' => $billId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found',
            ], 404);
        }

        $transaction = $payment->transaction;

        // Only update if transaction is unpaid or waiting
        if (in_array($transaction->status, ['belum_bayar', 'menunggu'])) {
            if ($status === 'SUCCESSFUL') {
                DB::transaction(function () use ($transaction, $payment, $data, $status) {
                    $payment->update([
                        'status' => 'confirmed',
                        'gateway_status' => $status,
                        'gateway_response' => json_encode($data),
                        'confirmed_at' => now(),
                    ]);

                    $transaction->update([
                        'status' => 'diproses',
                    ]);

                    Log::info('Flip Webhook Processed Successfully', [
                        'transaction_id' => $transaction->id,
                        'bill_id' => $data['bill_id'],
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
