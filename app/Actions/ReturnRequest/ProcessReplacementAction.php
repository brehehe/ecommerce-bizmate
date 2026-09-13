<?php

namespace App\Actions\ReturnRequest;

use App\Models\Notification;
use App\Models\ProductStock;
use App\Models\ReturnRequest;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProcessReplacementAction
{
    /**
     * Process item replacement for return.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(ReturnRequest $return, array $data, User $adminUser): array
    {
        if ($return->status !== 'barang_diterima_toko') {
            return ['success' => false, 'message' => 'Pengiriman barang pengganti hanya dapat diproses setelah barang diterima toko.'];
        }

        if (! in_array($return->type, ['tukar_barang', 'penggantian_barang', 'replacement'])) {
            return ['success' => false, 'message' => 'Aksi ini hanya untuk retur jenis tukar barang.'];
        }

        DB::transaction(function () use ($return, $data, $adminUser) {
            $origTx = $return->transaction;

            $newTx = Transaction::create([
                'transaction_number' => Transaction::generateNumber(),
                'user_id' => $return->user_id,
                'customer_address_id' => $origTx->customer_address_id,
                'payment_method_id' => $origTx->payment_method_id,
                'status' => 'diproses',
                'is_replacement_transaction' => true,
                'original_transaction_id' => $origTx->id,
                'subtotal' => 0,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'grand_total' => 0,
                'shipping_courier' => $data['replacement_courier_name'] ?? $origTx->shipping_courier,
                'shipping_service' => 'Penggantian Retur',
                'tracking_number' => $data['replacement_tracking_number'] ?? null,
                'notes' => 'Penggantian barang retur #'.$return->return_number.' dari transaksi #'.$origTx->transaction_number,
            ]);

            $return->load('items.transactionItem');
            foreach ($return->items as $rItem) {
                $txItem = $rItem->transactionItem;
                $productId = $txItem ? $txItem->product_id : $rItem->product_id;
                $productVariantId = $txItem ? $txItem->product_variant_id : $rItem->product_variant_id;
                $productName = $txItem ? $txItem->product_name : $rItem->product_name;
                $productSku = $txItem ? $txItem->product_sku : null;
                $variantName = $txItem ? $txItem->variant_name : null;
                $productImage = $txItem ? $txItem->product_image : null;
                $hpp = $txItem ? $txItem->hpp : 0;

                TransactionItem::create([
                    'transaction_id' => $newTx->id,
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId,
                    'product_name' => $productName.' (Penggantian Retur)',
                    'product_sku' => $productSku,
                    'variant_name' => $variantName,
                    'product_image' => $productImage,
                    'quantity' => $rItem->quantity_returned,
                    'hpp' => $hpp,
                    'harga_jual' => 0,
                    'diskon_item' => 0,
                    'harga_akhir' => 0,
                    'subtotal' => 0,
                ]);

                $stockRecord = $productVariantId
                    ? ProductStock::where('product_variant_id', $productVariantId)->first()
                    : ProductStock::where('product_id', $productId)->whereNull('product_variant_id')->first();

                if ($stockRecord && ! $stockRecord->is_unlimited) {
                    $stockBefore = $stockRecord->stock;
                    $stockAfter = max(0, $stockBefore - $rItem->quantity_returned);
                    $stockRecord->update(['stock' => $stockAfter]);

                    StockMovement::create([
                        'product_id' => $productId,
                        'product_variant_id' => $productVariantId,
                        'transaction_id' => $newTx->id,
                        'type' => 'keluar',
                        'quantity' => -$rItem->quantity_returned,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'notes' => 'Pengiriman barang pengganti retur - '.$return->return_number,
                        'created_by' => $adminUser->id,
                    ]);
                }
            }

            $return->update([
                'status' => 'selesai',
                'replacement_tracking_number' => $data['replacement_tracking_number'] ?? null,
                'replacement_courier_name' => $data['replacement_courier_name'] ?? null,
                'replacement_transaction_id' => $newTx->id,
                'notes_admin' => $data['notes_admin'] ?? $return->notes_admin,
            ]);

            $origTx->update(['return_status' => 'selesai']);

            try {
                $trackingInfo = ! empty($data['replacement_tracking_number'])
                    ? ' dengan nomor resi '.$data['replacement_tracking_number'].' ('.($data['replacement_courier_name'] ?? 'Kurir').')'
                    : '';

                Notification::create([
                    'user_id' => $return->user_id,
                    'title' => 'Barang Pengganti Dikirim',
                    'message' => 'Barang pengganti untuk retur #'.$return->return_number.' telah dikirim'.$trackingInfo.'. Nomor transaksi pengganti: #'.$newTx->transaction_number.'.',
                    'type' => 'return_replacement_shipped',
                    'url' => '/transactions/'.$newTx->id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }
        });

        return ['success' => true];
    }
}
