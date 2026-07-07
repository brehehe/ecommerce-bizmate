<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KomerceWebhookController extends Controller
{
    /**
     * Handle incoming callback/webhook from Komerce (shipping or payment).
     *
     * Idempotency: if the same event has already been processed, we return 200
     * immediately without re-processing, preventing duplicate status updates.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        Log::info('Komerce Webhook Received', [
            'body' => $request->all(),
        ]);

        // 1. Check if this is a shipping status callback
        $bookingCode = $request->input('booking_code') ?? $request->input('order_no');
        $airwayBill = $request->input('airway_bill') ?? $request->input('tracking_number') ?? $request->input('cnote');

        $transaction = null;
        if ($bookingCode) {
            $transaction = Transaction::where('booking_code', $bookingCode)->first();
        }
        if (! $transaction && $airwayBill) {
            $transaction = Transaction::where('tracking_number', $airwayBill)->first();
        }

        if ($transaction && ($bookingCode || $airwayBill)) {
            $shippingStatus = strtolower($request->input('shipping_status') ?? $request->input('status') ?? '');

            // --- Idempotency Check for Shipping Webhook ---
            $shippingRef = $bookingCode ?? $airwayBill;
            $idempotencyKey = "komerce:shipping:{$shippingRef}:{$shippingStatus}";

            if (WebhookEvent::alreadyProcessed($idempotencyKey)) {
                Log::info("Komerce Shipping Webhook: duplicate event skipped [{$idempotencyKey}]");

                return response()->json([
                    'status' => 'success',
                    'message' => 'Webhook already processed (duplicate skipped)',
                ]);
            }

            Log::info("Komerce Shipping Webhook: Transaction {$transaction->transaction_number} status is {$shippingStatus}");

            if (in_array($shippingStatus, ['delivered', 'diterima', 'success', 'selesai', 'arrived'])) {
                if ($transaction->status !== 'selesai') {
                    $transaction->update(['status' => 'selesai']);
                }
            } elseif (in_array($shippingStatus, ['shipping', 'dikirim', 'on_delivery', 'transit', 'picked_up', 'pickup', 'dijemput', 'diserahkan', 'paket_diterima_kurir', 'hub', 'sorting_center', 'dalam_perjalanan'])) {
                if (in_array($transaction->status, ['diproses', 'dikemas', 'out_for_pickup'])) {
                    $transaction->update(['status' => 'dikirim']);
                }
            } elseif (in_array($shippingStatus, ['cancelled', 'batal', 'returned', 'retur', 'gagal', 'dibatalkan'])) {
                if (! in_array($transaction->status, ['batal', 'selesai'])) {
                    DB::transaction(function () use ($transaction) {
                        $this->restoreStock($transaction);
                        $transaction->update([
                            'status' => 'batal',
                            'cancel_reason' => 'Dibatalkan oleh kurir/sistem pengiriman Komerce',
                            'cancelled_at' => now(),
                        ]);
                    });
                }
            }

            WebhookEvent::record('komerce', $idempotencyKey, $request->all(), 200);

            return response()->json([
                'status' => 'success',
                'message' => 'Shipping webhook processed',
            ]);
        }

        // 2. Otherwise, treat as payment status callback
        $referenceId = $request->input('reference_id') ?? $request->input('external_id') ?? $request->input('order_id');
        $status = strtolower($request->input('status') ?? '');

        if (! $referenceId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing reference_id',
            ], 400);
        }

        // --- Idempotency Check for Payment Webhook ---
        $idempotencyKey = "komerce:payment:{$referenceId}:{$status}";

        if (WebhookEvent::alreadyProcessed($idempotencyKey)) {
            Log::info("Komerce Payment Webhook: duplicate event skipped [{$idempotencyKey}]");

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook already processed (duplicate skipped)',
            ]);
        }

        // Find payment by gateway_transaction_id
        $payment = TransactionPayment::where('gateway_transaction_id', $referenceId)
            ->where('status', 'pending')
            ->first();

        if (! $payment) {
            // Fallback: search by transaction number
            $transaction = Transaction::where('transaction_number', $referenceId)->first();
            if ($transaction) {
                $payment = $transaction->payment;
            }
        }

        if (! $payment) {
            Log::warning('Komerce Webhook: Payment Record Not Found', [
                'reference_id' => $referenceId,
            ]);

            WebhookEvent::record('komerce', $idempotencyKey, $request->all(), 404);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found',
            ], 404);
        }

        $transaction = $payment->transaction;

        // Only update if transaction is unpaid or waiting
        if (in_array($transaction->status, ['belum_bayar', 'menunggu'])) {
            if ($status === 'paid' || $status === 'success' || $status === 'successful') {
                DB::transaction(function () use ($transaction, $payment, $request, $status) {
                    $payment->update([
                        'status' => 'confirmed',
                        'gateway_status' => strtoupper($status),
                        'gateway_response' => json_encode($request->all()),
                        'confirmed_at' => now(),
                    ]);

                    $transaction->update([
                        'status' => 'diproses',
                    ]);

                    Log::info('Komerce Payment Webhook Processed Successfully', [
                        'transaction_id' => $transaction->id,
                        'reference_id' => $referenceId,
                    ]);
                });
            }
        }

        WebhookEvent::record('komerce', $idempotencyKey, $request->all(), 200);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment webhook processed',
        ], 200);
    }

    /**
     * Restore stock when a transaction is cancelled.
     */
    private function restoreStock(Transaction $transaction): void
    {
        $transaction->load('items');

        foreach ($transaction->items as $item) {
            if ($item->is_gift_item) {
                continue;
            }

            $stockRecord = $item->product_variant_id
                ? ProductStock::where('product_variant_id', $item->product_variant_id)->first()
                : ProductStock::where('product_id', $item->product_id)->whereNull('product_variant_id')->first();

            if ($stockRecord && ! $stockRecord->is_unlimited) {
                $stockBefore = $stockRecord->stock;
                $stockAfter = $stockBefore + $item->quantity;
                $stockRecord->update(['stock' => $stockAfter]);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'transaction_id' => $transaction->id,
                    'type' => 'retur',
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'notes' => 'Pembatalan otomatis via Webhook Komerce - '.$transaction->transaction_number,
                    'created_by' => null,
                ]);
            }
        }
    }
}
