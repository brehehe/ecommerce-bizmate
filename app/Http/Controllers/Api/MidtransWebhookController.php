<?php

namespace App\Http\Controllers\Api;

use App\Events\ListingPaymentConfirmed;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductListingPayment;
use App\Models\Setting;
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

        // ── LISTING PAYMENT (Product Listing Fee) ──────────────────────────
        // Order IDs prefixed with LISTING- are for product listing fees, not
        // regular transactions. Handle them separately.
        if (str_starts_with($orderId, 'LISTING-')) {
            return $this->handleListingPayment($request, $orderId, $statusCode, $grossAmount, $signatureKey);
        }

        // ── REGULAR TRANSACTION PAYMENT ────────────────────────────────────
        $actualOrderId = $orderId;
        if (str_contains($orderId, '-')) {
            $lastDashPos = strrpos($orderId, '-');
            $potentialSuffix = substr($orderId, $lastDashPos + 1);
            if (is_numeric($potentialSuffix)) {
                $actualOrderId = substr($orderId, 0, $lastDashPos);
            }
        }

        $transaction = Transaction::where('transaction_number', $actualOrderId)->first();

        if (! $transaction) {
            Log::warning('Midtrans Webhook: Transaction Not Found', [
                'order_id' => $orderId,
                'parsed_actual_order_id' => $actualOrderId,
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

    /**
     * Handle Midtrans webhook for product listing fee payments (LISTING-* prefix).
     * When Midtrans sends a settlement notification, automatically activate the product listing.
     */
    private function handleListingPayment(
        Request $request,
        string $orderId,
        ?string $statusCode,
        ?string $grossAmount,
        ?string $signatureKey,
    ): JsonResponse {
        $listingPayment = ProductListingPayment::where('order_id', $orderId)->first();

        if (! $listingPayment) {
            Log::warning('Midtrans Webhook: Listing Payment Not Found', ['order_id' => $orderId]);

            return response()->json(['status' => 'error', 'message' => 'Listing payment not found'], 404);
        }

        // Verify Midtrans Signature Key using the server key from settings
        $serverKey = Setting::where('key', 'midtrans_server_key')->value('value')
            ?: config('app.midtrans.server_key', '');

        if ($signatureKey && $statusCode && $grossAmount && $serverKey) {
            $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
            if ($signatureKey !== $expectedSignature) {
                Log::warning('Midtrans Webhook: Listing Payment Invalid Signature', ['order_id' => $orderId]);

                return response()->json(['status' => 'error', 'message' => 'Invalid Signature'], 401);
            }
        }

        $transactionStatus = $request->input('transaction_status');
        $fraudStatus = $request->input('fraud_status');

        $isSuccess = ($transactionStatus === 'settlement') ||
                     ($transactionStatus === 'capture' && $fraudStatus === 'accept');

        $isFailed = in_array($transactionStatus, ['cancel', 'deny', 'expire']);

        if ($listingPayment->status !== 'pending') {
            // Already processed — return 200 to prevent Midtrans retry
            return response()->json(['status' => 'success', 'message' => 'Already processed'], 200);
        }

        if ($isSuccess) {
            DB::transaction(function () use ($listingPayment, $request) {
                $product = Product::find($listingPayment->product_id);

                if ($product) {
                    $baseDate = ($product->listing_expires_at && $product->listing_expires_at->isFuture())
                        ? $product->listing_expires_at
                        : now();

                    $newExpiresAt = (clone $baseDate)->addDays($listingPayment->days);

                    $product->update([
                        'listing_expires_at' => $newExpiresAt,
                        'listing_fee' => (float) $product->listing_fee + (float) $listingPayment->amount,
                        'listing_days' => (int) $product->listing_days + $listingPayment->days,
                        'active' => true,
                    ]);
                }

                $listingPayment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'gateway_transaction_id' => $request->input('transaction_id'),
                    'gateway_response' => $request->all(),
                ]);

                Log::info('Midtrans Webhook: Listing Payment Auto-Confirmed', [
                    'order_id' => $listingPayment->order_id,
                    'product_id' => $listingPayment->product_id,
                    'days' => $listingPayment->days,
                ]);
            });

            // Broadcast real-time event via Reverb WebSockets
            event(new ListingPaymentConfirmed($listingPayment->fresh()));
        } elseif ($isFailed) {
            $listingPayment->update([
                'status' => 'failed',
                'gateway_response' => $request->all(),
            ]);

            Log::info('Midtrans Webhook: Listing Payment Failed/Expired', [
                'order_id' => $orderId,
            ]);

            event(new ListingPaymentConfirmed($listingPayment->fresh()));
        }

        return response()->json(['status' => 'success', 'message' => 'Listing webhook processed'], 200);
    }
}
