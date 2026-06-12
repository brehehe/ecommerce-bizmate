<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BiteshipWebhookController extends Controller
{
    /**
     * Handle incoming callback/webhook from Biteship.
     *
     * Idempotency: if the same event (order_id + status) has already been
     * processed, we return 200 immediately without re-processing.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        Log::info('Biteship Webhook Received', [
            'body' => $request->all(),
        ]);

        $orderId = $request->input('order_id') ?? $request->input('id');
        $waybillId = $request->input('courier.waybill_id') ?? $request->input('waybill_id');
        $status = strtolower($request->input('status') ?? '');

        if (! $orderId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing order_id or id in payload',
            ], 400);
        }

        // --- Idempotency Check ---
        // Build a key that uniquely identifies this specific event.
        // If Biteship retries the same event we should not process it twice.
        $idempotencyKey = "biteship:{$orderId}:{$status}";

        if (WebhookEvent::alreadyProcessed($idempotencyKey)) {
            Log::info("Biteship Webhook: duplicate event skipped [{$idempotencyKey}]");

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook already processed (duplicate skipped)',
            ]);
        }

        // Find transaction by booking code or airway bill
        $transaction = Transaction::where('booking_code', $orderId)->first();
        if (! $transaction && $waybillId) {
            $transaction = Transaction::where('tracking_number', $waybillId)->first();
        }

        if (! $transaction) {
            Log::warning('Biteship Webhook: Transaction not found for order_id: '.$orderId);

            // Still record to avoid retrying a permanently-missing reference
            WebhookEvent::record('biteship', $idempotencyKey, $request->all(), 404);

            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found',
            ], 404);
        }

        Log::info("Biteship Shipping Webhook: Transaction {$transaction->transaction_number} status is {$status}");

        // Handle status updates
        if (in_array($status, ['delivered'])) {
            if ($transaction->status !== 'selesai') {
                $transaction->update(['status' => 'selesai']);
                Log::info("Biteship Webhook: Transaction {$transaction->transaction_number} status updated to [selesai].");
            }
        } elseif (in_array($status, ['picked', 'picked_up', 'dropping_off', 'droppingoff', 'in_transit', 'intransit'])) {
            if (in_array($transaction->status, ['diproses', 'dikemas', 'out_for_pickup'])) {
                $transaction->update(['status' => 'dikirim']);
                Log::info("Biteship Webhook: Transaction {$transaction->transaction_number} status updated to [dikirim].");
            }
        } elseif (in_array($status, ['cancelled', 'rejected', 'courier_not_found', 'couriernotfound', 'returned', 'disposed'])) {
            if (! in_array($transaction->status, ['batal', 'selesai'])) {
                DB::transaction(function () use ($transaction, $status) {
                    $this->restoreStock($transaction);
                    $transaction->update([
                        'status' => 'batal',
                        'cancel_reason' => 'Dibatalkan/Ditolak/Retur oleh sistem pengiriman Biteship (Status: '.$status.')',
                        'cancelled_at' => now(),
                    ]);
                });
                Log::info("Biteship Webhook: Transaction {$transaction->transaction_number} cancelled and stock restored.");
            }
        }

        // Mark this event as processed so future retries are ignored
        WebhookEvent::record('biteship', $idempotencyKey, $request->all(), 200);

        return response()->json([
            'status' => 'success',
            'message' => 'Biteship webhook processed',
        ]);
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
                    'notes' => 'Pembatalan otomatis via Webhook Biteship - '.$transaction->transaction_number,
                    'created_by' => null,
                ]);
            }
        }
    }
}
