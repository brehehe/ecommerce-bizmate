<?php

namespace App\Actions\Transaction;

use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateTransactionStatusAction
{
    /**
     * Update transaction status with stock restore on cancellation.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(Transaction $transaction, string $newStatus, ?string $cancelReason, ?User $user): array
    {
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return ['success' => false, 'message' => 'Transaksi yang sudah selesai atau batal tidak dapat diubah statusnya lagi.'];
        }

        $statusOrder = [
            'belum_bayar',
            'menunggu',
            'diproses',
            'dikemas',
            'out_for_pickup',
            'dikirim',
            'selesai',
        ];

        $currentIndex = array_search($transaction->status, $statusOrder);
        $newIndex = array_search($newStatus, $statusOrder);

        if ($newStatus !== 'batal' && $currentIndex !== false && $newIndex !== false && $newIndex < $currentIndex) {
            return ['success' => false, 'message' => 'Status transaksi tidak dapat diubah kembali ke status sebelumnya.'];
        }

        DB::transaction(function () use ($transaction, $newStatus, $cancelReason, $user) {
            if ($newStatus === 'batal' && $transaction->status !== 'batal') {
                $this->restoreStock($transaction, $user);
                $transaction->update([
                    'status' => $newStatus,
                    'cancel_reason' => $cancelReason,
                    'cancelled_at' => now(),
                ]);
            } else {
                $transaction->update(['status' => $newStatus]);
            }
        });

        return ['success' => true];
    }

    /**
     * Restore stock when a transaction is cancelled.
     */
    public function restoreStock(Transaction $transaction, ?User $adminUser): void
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
                    'notes' => 'Pembatalan transaksi - '.$transaction->transaction_number,
                    'created_by' => $adminUser?->id,
                ]);
            }
        }
    }
}
