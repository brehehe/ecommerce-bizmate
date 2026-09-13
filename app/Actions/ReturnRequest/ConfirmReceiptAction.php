<?php

namespace App\Actions\ReturnRequest;

use App\Models\Notification;
use App\Models\ProductStock;
use App\Models\ReturnRequest;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmReceiptAction
{
    /**
     * Confirm receipt of return items by the store.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(ReturnRequest $return, string $stockAction, User $adminUser): array
    {
        if ($return->status !== 'barang_dikirim_customer') {
            return ['success' => false, 'message' => 'Konfirmasi penerimaan hanya dapat dilakukan setelah barang dikirim customer.'];
        }

        DB::transaction(function () use ($return, $stockAction, $adminUser) {
            $return->update([
                'status' => 'barang_diterima_toko',
                'received_by' => $adminUser->id,
                'received_at' => now(),
            ]);

            $return->transaction->update(['return_status' => 'barang_diterima_toko']);

            $return->load('items');
            foreach ($return->items as $item) {
                $stockRecord = $item->product_variant_id
                    ? ProductStock::where('product_variant_id', $item->product_variant_id)->first()
                    : ProductStock::where('product_id', $item->product_id)->whereNull('product_variant_id')->first();

                if ($stockRecord && ! $stockRecord->is_unlimited) {
                    $stockBefore = $stockRecord->stock;

                    if ($stockAction === 'active') {
                        $stockAfter = $stockBefore + $item->quantity_returned;
                        $stockRecord->update(['stock' => $stockAfter]);
                        $notes = 'Retur barang (kembali ke stok aktif) - '.$return->return_number;
                    } else {
                        $stockAfter = $stockBefore;
                        $notes = 'Retur barang (rusak/tidak dikembalikan ke stok) - '.$return->return_number;
                    }

                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'transaction_id' => $return->transaction_id,
                        'type' => 'retur',
                        'quantity' => $stockAction === 'active' ? $item->quantity_returned : 0,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'notes' => $notes,
                        'created_by' => $adminUser->id,
                    ]);
                }
            }

            try {
                Notification::create([
                    'user_id' => $return->user_id,
                    'title' => 'Barang Retur Diterima',
                    'message' => 'Barang retur Anda (#'.$return->return_number.') telah diterima oleh toko. Admin sedang memproses '.($return->type === 'refund' ? 'pengembalian dana' : 'pengiriman barang pengganti').'.',
                    'type' => 'return_received',
                    'url' => '/transactions/'.$return->transaction_id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }
        });

        return ['success' => true];
    }
}
