<?php

namespace App\Actions\Transaction;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAdminTransactionAction
{
    /**
     * Create POS or cashier transaction from admin panel.
     *
     * @param  array<string, mixed>  $validated
     */
    public function execute(array $validated, User $creator): Transaction
    {
        return DB::transaction(function () use ($validated, $creator) {
            $targetUserId = ! empty($validated['user_id']) ? $validated['user_id'] : $creator->id;

            $datePrefix = now()->format('Ymd');
            $randomString = strtoupper(Str::random(5));
            $trxNumber = 'TRX-POS-'.$datePrefix.'-'.$randomString;

            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $itemInput) {
                $product = Product::with(['productPrice', 'images', 'variants.productPrice', 'variants.options'])->findOrFail($itemInput['product_id']);
                $unitPrice = (float) $itemInput['unit_price'];
                $qty = (int) $itemInput['quantity'];
                $itemSubtotal = $unitPrice * $qty;
                $subtotal += $itemSubtotal;

                $variantId = $itemInput['variant_id'] ?? null;
                $variantName = null;
                $sku = $product->sku ?: ('SKU-'.$product->id);
                $productImage = $product->images->first()?->url ?? $product->images->first()?->path ?? $product->image;

                if ($variantId) {
                    $matchedVar = $product->variants->firstWhere('id', $variantId);
                    if ($matchedVar) {
                        $variantName = $matchedVar->options->pluck('value')->join(', ') ?: $matchedVar->sku;
                        if (! empty($matchedVar->sku)) {
                            $sku = $matchedVar->sku;
                        }
                    }
                }

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'product_name' => $product->name,
                    'product_sku' => $sku,
                    'variant_name' => $variantName,
                    'product_image' => $productImage,
                    'harga_jual' => $unitPrice,
                    'harga_akhir' => $unitPrice,
                    'diskon_item' => 0,
                    'hpp' => 0,
                    'quantity' => $qty,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
            $discountAmount = (float) ($validated['discount_amount'] ?? 0);

            $taxEnabled = filter_var(Setting::where('key', 'tax_enabled')->value('value') ?? config('app.tax_enabled', false), FILTER_VALIDATE_BOOLEAN);
            $taxPct = (float) (Setting::where('key', 'tax_percentage')->value('value') ?? 0);
            $taxAmount = $taxEnabled ? round($subtotal * ($taxPct / 100)) : 0;

            $grandTotal = max(0, $subtotal + $shippingCost + $taxAmount - $discountAmount);

            $requestedStatus = $validated['status'] ?? null;
            if ($requestedStatus) {
                $status = $requestedStatus;
                $isPaid = in_array($status, ['selesai', 'diproses', 'dikirim']);
            } else {
                $isPaid = $validated['payment_status'] === 'paid';
                $status = $isPaid ? ($validated['delivery_type'] === 'direct_cashier' ? 'selesai' : 'diproses') : 'belum_bayar';
            }
            $paymentStatus = $isPaid ? 'paid' : ($validated['payment_status'] ?? 'unpaid');

            $transaction = Transaction::create([
                'user_id' => $targetUserId,
                'transaction_number' => $trxNumber,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_method_id' => $validated['payment_method_id'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'grand_total' => $grandTotal,
                'notes' => $validated['notes'] ?? 'Transaksi Kasir POS',
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $item['product_name'],
                    'product_sku' => $item['product_sku'],
                    'variant_name' => $item['variant_name'],
                    'product_image' => $item['product_image'],
                    'harga_jual' => $item['harga_jual'],
                    'harga_akhir' => $item['harga_akhir'],
                    'diskon_item' => $item['diskon_item'],
                    'hpp' => $item['hpp'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);

                if ($item['product_variant_id']) {
                    $stockRecord = ProductStock::where('product_variant_id', $item['product_variant_id'])->first();
                } else {
                    $stockRecord = ProductStock::where('product_id', $item['product_id'])->whereNull('product_variant_id')->first();
                }

                if ($stockRecord) {
                    $prevQty = (int) ($stockRecord->stock ?? 0);
                    $newQty = max(0, $prevQty - $item['quantity']);
                    $stockRecord->update(['stock' => $newQty]);

                    StockMovement::create([
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'],
                        'transaction_id' => $transaction->id,
                        'type' => 'keluar',
                        'quantity' => -$item['quantity'],
                        'stock_before' => $prevQty,
                        'stock_after' => $newQty,
                        'notes' => 'Penjualan POS - '.$trxNumber,
                        'created_by' => $creator->id,
                    ]);
                }
            }

            return $transaction;
        });
    }
}
