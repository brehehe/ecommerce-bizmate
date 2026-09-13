<?php

namespace App\Actions\Checkout;

use App\Models\CartItem;
use App\Models\CoinHistory;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\ProductStock;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\User;
use App\Services\KomerceService;
use App\Services\MembershipService;
use App\Services\MidtransService;
use App\Services\Promotion\PromotionCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProcessCheckoutAction
{
    public function __construct(
        protected MembershipService $membershipService,
        protected PromotionCalculationService $promotionService
    ) {}

    /**
     * Execute the checkout process.
     *
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CartItem>  $cartItems
     */
    public function execute(User $user, array $data, Collection $cartItems): Transaction
    {
        $address = null;
        if (! empty($data['customer_address_id'])) {
            $address = CustomerAddress::where('user_id', $user->id)
                ->findOrFail($data['customer_address_id']);
        }

        $paymentMethod = PaymentMethod::findOrFail($data['payment_method_id']);

        // Subtotal calculation
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $variant = $item->productVariant;
            $product = $item->product;

            $basePrice = $variant
                ? (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0))
                : (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));

            $effectivePrice = $basePrice;
            $tiers = $variant ? $variant->tierPrices : $product->tierPrices;
            if ($tiers && $tiers->isNotEmpty()) {
                $matchingTiers = $tiers->filter(fn ($t) => $item->quantity >= $t->min_qty)->sortByDesc('min_qty');
                if ($matchingTiers->isNotEmpty()) {
                    $appliedTier = $matchingTiers->first();
                    $tierPriceVal = (float) $appliedTier->price;
                    if ($tierPriceVal < $effectivePrice) {
                        $effectivePrice = $tierPriceVal;
                    }
                }
            }

            $item->unit_price = $effectivePrice;
            $item->subtotal = $effectivePrice * $item->quantity;
            $subtotal += $item->subtotal;
        }

        // Voucher resolution
        $voucherCode = $data['voucher_code'] ?? null;
        $voucherDiscountType = null;
        $voucherDiscountValue = null;
        $voucherPromotion = null;
        $discountAmount = 0;
        $shippingDiscount = 0;

        if ($voucherCode) {
            $result = $this->resolveVoucher($voucherCode, $cartItems, (float) ($data['shipping_fee'] ?? 0), $subtotal);
            if ($result['valid']) {
                $voucherPromotion = $result['promotion'];
                $voucherDiscountType = $result['discount_type'];
                $voucherDiscountValue = $result['discount_value'];
                $discountAmount = $result['discount_amount'];
                $shippingDiscount = $result['shipping_discount'];
            }
        }

        // Additional costs
        $additionalCostsJson = Setting::where('key', 'additional_costs')->value('value');
        $additionalCosts = $additionalCostsJson ? json_decode($additionalCostsJson, true) : [];
        $activeAdditionalCosts = [];
        $additionalCostsSum = 0.0;

        if (is_array($additionalCosts)) {
            foreach ($additionalCosts as $cost) {
                if (isset($cost['is_active']) && $cost['is_active']) {
                    $activeAdditionalCosts[] = [
                        'id' => $cost['id'] ?? '',
                        'name' => $cost['name'] ?? '',
                        'value' => (float) ($cost['value'] ?? 0),
                    ];
                    $additionalCostsSum += (float) ($cost['value'] ?? 0);
                }
            }
        }

        $shippingFee = (float) ($data['shipping_fee'] ?? 0);
        $adminFee = (float) ($paymentMethod->admin_fee ?? 0);
        $appFee = (float) (Setting::where('key', 'shipping_rate')->value('value') ?? 0);

        // Membership discount & free shipping
        $membershipDiscountAmount = 0.0;
        $membershipShippingDiscount = 0.0;
        $membershipLevelId = null;
        $membership = $user->membership()->with('level')->first();
        if ($membership && $membership->level?->apply_discount_at_checkout) {
            $memberBenefits = $this->membershipService->getMembershipCheckoutBenefits($user);
            if ($memberBenefits['type'] === 'percentage' && $memberBenefits['value'] > 0) {
                $membershipDiscountAmount = round($subtotal * ($memberBenefits['value'] / 100), 2);
                $membershipLevelId = $membership->membership_level_id;
            } elseif ($memberBenefits['type'] === 'nominal' && $memberBenefits['value'] > 0) {
                $membershipDiscountAmount = min((float) $memberBenefits['value'], $subtotal);
                $membershipLevelId = $membership->membership_level_id;
            }
            if ($memberBenefits['free_shipping']) {
                $membershipShippingDiscount = $shippingFee;
                if (! $membershipLevelId) {
                    $membershipLevelId = $membership->membership_level_id;
                }
            }
        }

        $grandTotal = $subtotal - $discountAmount - $membershipDiscountAmount + ($shippingFee - $shippingDiscount - $membershipShippingDiscount) + $adminFee + $appFee + $additionalCostsSum;
        $grandTotal = max(0, $grandTotal);

        // Loyalty Coins
        $coinsRedeemed = 0;
        $coinsValue = 0;
        $coinsEnabled = Setting::where('key', 'coins_enabled')->value('value') === '1';

        if ($coinsEnabled && ! empty($data['use_coins']) && $user->coins_balance > 0) {
            $coinConversionRate = (float) (Setting::where('key', 'coin_conversion_rate')->value('value') ?? 1);
            $coinMinPurchaseRedeem = (float) (Setting::where('key', 'coin_min_purchase_redeem')->value('value') ?? 0);
            $coinMaxRedeemPerTxn = (float) (Setting::where('key', 'coin_max_redeem_per_txn')->value('value') ?? 50000);
            $coinMaxRedeemPercentage = (float) (Setting::where('key', 'coin_max_redeem_percentage')->value('value') ?? 100);

            if ($subtotal >= $coinMinPurchaseRedeem) {
                $maxCoinsByPercentage = ($subtotal * ($coinMaxRedeemPercentage / 100)) / $coinConversionRate;
                $maxRedeemableCoins = min($user->coins_balance, $coinMaxRedeemPerTxn, $maxCoinsByPercentage);
                $maxDiscountValue = min($maxRedeemableCoins * $coinConversionRate, $grandTotal);

                if ($maxDiscountValue > 0) {
                    $coinsRedeemed = (int) floor($maxDiscountValue / $coinConversionRate);
                    $coinsValue = $coinsRedeemed * $coinConversionRate;
                    $grandTotal = max(0, $grandTotal - $coinsValue);
                }
            }
        }

        // Coins Earned
        $coinsEarned = 0;
        if ($coinsEnabled) {
            $pointsVoucher = null;
            if ($voucherPromotion) {
                if (is_array($voucherPromotion) || $voucherPromotion instanceof Collection) {
                    foreach ($voucherPromotion as $promo) {
                        if ($promo->settings['is_points_voucher'] ?? false) {
                            $pointsVoucher = $promo;
                            break;
                        }
                    }
                } elseif ($voucherPromotion instanceof Promotion) {
                    if ($voucherPromotion->settings['is_points_voucher'] ?? false) {
                        $pointsVoucher = $voucherPromotion;
                    }
                }
            }

            if ($pointsVoucher) {
                $isNewUser = ! Transaction::where('user_id', $user->id)
                    ->where('status', '!=', 'batal')
                    ->exists();

                $pointsNew = (int) ($pointsVoucher->settings['points_new_user'] ?? 0);
                $pointsExisting = (int) ($pointsVoucher->settings['points_existing_user'] ?? 0);

                $coinsEarned = $isNewUser ? $pointsNew : $pointsExisting;
            } elseif ($coinsRedeemed <= 0) {
                $coinEarningMethod = Setting::where('key', 'coin_earning_method')->value('value') ?? 'proportional';

                if ($coinEarningMethod === 'proportional') {
                    $coinEarningRateRupiah = (float) (Setting::where('key', 'coin_earning_rate_rupiah')->value('value') ?? 1000);
                    $coinEarningRateCoins = (float) (Setting::where('key', 'coin_earning_rate_coins')->value('value') ?? 1);

                    if ($coinEarningRateRupiah > 0) {
                        $coinsEarned = (int) (floor($subtotal / $coinEarningRateRupiah) * $coinEarningRateCoins);
                    }
                } elseif ($coinEarningMethod === 'tiered') {
                    $tiersVal = Setting::where('key', 'coin_earning_tiers')->value('value');
                    $coinEarningTiers = $tiersVal ? json_decode($tiersVal, true) : [];

                    if (is_array($coinEarningTiers) && count($coinEarningTiers) > 0) {
                        usort($coinEarningTiers, fn ($a, $b) => ($b['min_purchase'] ?? 0) <=> ($a['min_purchase'] ?? 0));

                        foreach ($coinEarningTiers as $tier) {
                            $minPurchase = (float) ($tier['min_purchase'] ?? 0);
                            $earnCoins = (int) ($tier['earn_coins'] ?? 0);

                            if ($subtotal >= $minPurchase) {
                                $coinsEarned = $earnCoins;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return DB::transaction(function () use (
            $user,
            $address,
            $paymentMethod,
            $cartItems,
            $data,
            $subtotal,
            $discountAmount,
            $shippingFee,
            $shippingDiscount,
            $adminFee,
            $appFee,
            $activeAdditionalCosts,
            $grandTotal,
            $voucherCode,
            $voucherDiscountType,
            $voucherDiscountValue,
            $voucherPromotion,
            $coinsRedeemed,
            $coinsValue,
            $coinsEarned,
            $membershipDiscountAmount,
            $membershipLevelId
        ) {
            $transaction = Transaction::create([
                'transaction_number' => Transaction::generateNumber(),
                'user_id' => $user->id,
                'customer_address_id' => $address?->id,
                'payment_method_id' => $paymentMethod->id,
                'courier_id' => $data['courier_id'] ?? null,
                'status' => 'belum_bayar',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_fee' => $shippingFee,
                'shipping_discount' => $shippingDiscount,
                'admin_fee' => $adminFee,
                'application_fee' => $appFee,
                'additional_costs' => $activeAdditionalCosts,
                'grand_total' => $grandTotal,
                'shipping_courier' => $data['shipping_courier'],
                'shipping_service' => $data['shipping_service'],
                'shipping_etd' => $data['shipping_etd'] ?? null,
                'voucher_code' => $voucherCode,
                'voucher_discount_type' => $voucherDiscountType,
                'voucher_discount_value' => $voucherDiscountValue,
                'notes' => $data['notes'] ?? null,
                'coins_redeemed' => $coinsRedeemed,
                'coins_value' => $coinsValue,
                'coins_earned' => $coinsEarned,
                'membership_discount_amount' => $membershipDiscountAmount,
                'membership_level_id' => $membershipLevelId,
            ]);

            if ($coinsRedeemed > 0) {
                $user->decrement('coins_balance', $coinsRedeemed);
                CoinHistory::create([
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'amount' => -$coinsRedeemed,
                    'type' => 'redeem',
                    'description' => 'Penggunaan Poin untuk transaksi #'.$transaction->transaction_number,
                ]);
            }

            $transaction->statusHistories()->create([
                'status' => 'belum_bayar',
                'description' => 'Pesanan berhasil dibuat, menunggu pembayaran.',
                'created_by' => $user->id,
            ]);

            foreach ($cartItems as $item) {
                $product = $item->product;
                $variant = $item->productVariant;

                if ($variant) {
                    $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                    if ($matchedVariant) {
                        $variant = $matchedVariant;
                    }

                    $hargaJual = (float) ($item->unit_price ?? ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0)));
                    $hpp = (float) ($variant->productPrice?->cost ?? 0);
                    $variantName = $variant->options->pluck('name')->join(' / ');
                    $sku = $variant->sku;
                } else {
                    $hargaJual = (float) ($item->unit_price ?? ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0)));
                    $hpp = (float) ($product->productPrice?->cost ?? 0);
                    $variantName = null;
                    $sku = $product->sku;
                }

                $diskonItem = 0;
                $hargaAkhir = $hargaJual - $diskonItem;
                $itemSubtotal = $hargaAkhir * $item->quantity;
                $productImage = $product->images->first()?->path ?? $product->image;

                $appliedPromotionId = $variant ? ($variant->applied_promotion_id ?? null) : ($product->applied_promotion_id ?? null);
                $promoQtyUsed = $variant ? ($variant->promo_quantity_used ?? null) : ($product->promo_quantity_used ?? null);

                $itemNotes = $data['item_notes'] ?? [];
                $note = $itemNotes[$item->id] ?? null;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'product_sku' => $sku,
                    'variant_name' => $variantName,
                    'product_image' => $productImage,
                    'quantity' => $item->quantity,
                    'hpp' => $hpp,
                    'harga_jual' => $hargaJual,
                    'diskon_item' => $diskonItem,
                    'harga_akhir' => $hargaAkhir,
                    'subtotal' => $itemSubtotal,
                    'applied_promotion_id' => $appliedPromotionId,
                    'promo_quantity_used' => $promoQtyUsed,
                    'note' => $note,
                ]);

                $stockRecord = $variant
                    ? ProductStock::where('product_variant_id', $variant->id)->first()
                    : ProductStock::where('product_id', $product->id)->whereNull('product_variant_id')->first();

                if ($stockRecord && ! $stockRecord->is_unlimited) {
                    $stockBefore = $stockRecord->stock;
                    $stockAfter = max(0, $stockBefore - $item->quantity);
                    $stockRecord->update(['stock' => $stockAfter]);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'transaction_id' => $transaction->id,
                        'type' => 'keluar',
                        'quantity' => -$item->quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'notes' => 'Penjualan - '.$transaction->transaction_number,
                        'created_by' => $user->id,
                    ]);
                }
            }

            // Payment creation
            $paymentData = [
                'transaction_id' => $transaction->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $grandTotal,
                'status' => 'pending',
            ];

            if ($paymentMethod->type === 'gateway') {
                $methodName = strtolower($paymentMethod->name);
                if ($methodName === 'qris (komerce)') {
                    try {
                        $response = KomerceService::generateQris($transaction->transaction_number, $grandTotal);
                        if ($response['success']) {
                            $paymentData['gateway_transaction_id'] = $response['reference_id'] ?? $transaction->transaction_number;
                            $paymentData['gateway_status'] = 'PENDING';
                            $paymentData['gateway_response'] = json_encode([
                                'qris_string' => $response['qris_string'] ?? '',
                                'qris_image' => $response['qris_image'] ?? '',
                                'simulated' => $response['simulated'] ?? false,
                            ]);
                        } else {
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $response['error'] ?? 'Gagal generate pembayaran QRIS Komerce.']);
                        }
                    } catch (\Exception $e) {
                        $paymentData['gateway_status'] = 'FAILED_API';
                        $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                    }
                } elseif ($methodName === 'komerce payment') {
                    try {
                        $response = KomerceService::generatePaymentUrl($transaction->transaction_number, $grandTotal);
                        if ($response['success']) {
                            $paymentData['gateway_transaction_id'] = $response['reference_id'] ?? $transaction->transaction_number;
                            $paymentData['gateway_status'] = 'PENDING';
                            $paymentData['gateway_response'] = json_encode([
                                'checkout_url' => $response['checkout_url'] ?? '',
                                'simulated' => $response['simulated'] ?? false,
                            ]);
                        } else {
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $response['error'] ?? 'Gagal generate URL Komerce Payment.']);
                        }
                    } catch (\Exception $e) {
                        $paymentData['gateway_status'] = 'FAILED_API';
                        $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                    }
                } else {
                    $isMidtrans = str_contains($methodName, 'midtrans');
                    if ($isMidtrans) {
                        $midtransPaymentTypeKey = $data['midtrans_payment_type_key'] ?? null;
                        if ($midtransPaymentTypeKey) {
                            try {
                                $result = MidtransService::charge(
                                    $transaction->transaction_number,
                                    (int) $grandTotal,
                                    $midtransPaymentTypeKey,
                                    [
                                        'name' => $user->name,
                                        'email' => $user->email,
                                        'phone' => $user->phone_number ?? '',
                                    ]
                                );

                                if ($result['success']) {
                                    $paymentData['gateway_transaction_id'] = $transaction->transaction_number;
                                    $paymentData['gateway_status'] = 'PENDING';
                                    $paymentData['gateway_response'] = json_encode(array_merge(
                                        $result['raw'] ?? [],
                                        ['_payment_instructions' => $result['data'] ?? []]
                                    ));
                                } else {
                                    $paymentData['gateway_status'] = 'FAILED_API';
                                    $paymentData['gateway_response'] = json_encode(['error' => $result['error'] ?? 'Gagal menghubungi Midtrans Core API.']);
                                }
                            } catch (\Exception $e) {
                                $paymentData['gateway_status'] = 'FAILED_API';
                                $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                            }
                        } else {
                            try {
                                $serverKey = $paymentMethod->api_key ?: KomerceService::getSetting('midtrans_server_key', 'app.midtrans.server_key');
                                $baseUrl = ($paymentMethod->settings && isset($paymentMethod->settings['url']))
                                    ? $paymentMethod->settings['url']
                                    : KomerceService::getSetting('midtrans_snap_url', 'app.midtrans.snap_url');

                                $midtransUrl = rtrim($baseUrl, '/').'/snap/v1/transactions';
                                $payload = [
                                    'transaction_details' => [
                                        'order_id' => $transaction->transaction_number,
                                        'gross_amount' => (int) $grandTotal,
                                    ],
                                    'customer_details' => [
                                        'first_name' => $user->name,
                                        'email' => $user->email,
                                    ],
                                    'callbacks' => [
                                        'finish' => route('transactions.show', $transaction->id),
                                        'unfinish' => route('transactions.show', $transaction->id),
                                        'error' => route('transactions.show', $transaction->id),
                                    ],
                                ];

                                $response = Http::withBasicAuth($serverKey, '')->timeout(15)->post($midtransUrl, $payload);
                                if ($response->successful()) {
                                    $responseData = $response->json();
                                    $paymentData['gateway_transaction_id'] = $responseData['token'] ?? null;
                                    $paymentData['gateway_status'] = 'PENDING';
                                    $paymentData['gateway_response'] = json_encode($responseData);
                                } else {
                                    $paymentData['gateway_status'] = 'FAILED_API';
                                    $paymentData['gateway_response'] = json_encode(['error' => $response->json('error_messages')[0] ?? 'Gagal menghubungi Midtrans Snap.']);
                                }
                            } catch (\Exception $e) {
                                $paymentData['gateway_status'] = 'FAILED_API';
                                $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                            }
                        }
                    } elseif (str_contains($methodName, 'flip')) {
                        try {
                            $secretKey = $paymentMethod->api_key ?: config('app.flip.secret_key');
                            $baseUrl = ($paymentMethod->settings && isset($paymentMethod->settings['url']))
                                ? $paymentMethod->settings['url']
                                : config('app.flip.base_url', 'https://bigflip.id/big_sandbox_api');

                            $flipUrl = rtrim($baseUrl, '/').'/v2/pwf/bill';
                            $redirectUrl = env('FLIP_REDIRECT_URL') ?: route('transactions.show', $transaction->id);
                            if (str_ends_with($redirectUrl, '/')) {
                                $redirectUrl .= $transaction->id;
                            }
                            if (preg_match('/localhost|127\.0\.0\.1|192\.168\./i', $redirectUrl) || str_contains($redirectUrl, ':8000')) {
                                $redirectUrl = preg_replace('/^http:\/\/(?:localhost|127\.0\.0\.1|192\.168\.\d+\.\d+)(?::\d+)?/i', 'https://example.com', $redirectUrl);
                            }

                            $payload = [
                                'title' => 'Pembayaran Pesanan #'.$transaction->transaction_number,
                                'amount' => (int) $grandTotal,
                                'type' => 'SINGLE',
                                'redirect_url' => $redirectUrl,
                                'sender_name' => $user->name,
                                'sender_email' => $user->email,
                            ];

                            $response = Http::withBasicAuth($secretKey, '')->timeout(15)->asForm()->post($flipUrl, $payload);
                            if ($response->successful()) {
                                $responseData = $response->json();
                                $paymentData['gateway_transaction_id'] = $responseData['link_id'] ?? null;
                                $paymentData['gateway_status'] = $responseData['status'] ?? 'ACTIVE';
                                $paymentData['gateway_response'] = json_encode($responseData);
                            } else {
                                $paymentData['gateway_status'] = 'FAILED_API';
                                $paymentData['gateway_response'] = json_encode(['error' => $response->json('message') ?? 'Gagal menghubungi Flip API.', 'response_body' => $response->body()]);
                            }
                        } catch (\Exception $e) {
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                        }
                    } else {
                        try {
                            $secretKey = $paymentMethod->api_secret ?: KomerceService::getSetting('xendit_secret_key', 'app.xendit.private_key');
                            $baseUrl = ($paymentMethod->settings && isset($paymentMethod->settings['url']))
                                ? $paymentMethod->settings['url']
                                : KomerceService::getSetting('xendit_url', 'app.xendit.url');

                            $xenditUrl = rtrim($baseUrl, '/').'/v2/invoices';
                            $payload = [
                                'external_id' => $transaction->transaction_number,
                                'amount' => (float) $grandTotal,
                                'payer_email' => $user->email,
                                'description' => 'Pembayaran Pesanan #'.$transaction->transaction_number.' di '.config('app.name'),
                                'success_redirect_url' => route('transactions.show', $transaction->id),
                                'failure_redirect_url' => route('transactions.show', $transaction->id),
                            ];

                            $response = Http::withBasicAuth($secretKey, '')->timeout(15)->post($xenditUrl, $payload);
                            if ($response->successful()) {
                                $responseData = $response->json();
                                $paymentData['gateway_transaction_id'] = $responseData['id'] ?? null;
                                $paymentData['gateway_status'] = $responseData['status'] ?? 'PENDING';
                                $paymentData['gateway_response'] = json_encode($responseData);
                            } else {
                                $paymentData['gateway_status'] = 'FAILED_API';
                                $paymentData['gateway_response'] = json_encode(['error' => $response->json('message') ?? 'Terjadi kesalahan saat menghubungkan ke Xendit.']);
                            }
                        } catch (\Exception $e) {
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            TransactionPayment::create($paymentData);

            if ($voucherPromotion) {
                if (is_iterable($voucherPromotion) || is_array($voucherPromotion)) {
                    foreach ($voucherPromotion as $promo) {
                        $promo->increment('used_count');
                    }
                } else {
                    $voucherPromotion->increment('used_count');
                }
            }

            if (session()->has('buy_now_item')) {
                session()->forget('buy_now_item');
            } else {
                CartItem::where('user_id', $user->id)
                    ->where('is_checked', true)
                    ->delete();
            }

            return $transaction;
        });
    }

    /**
     * Resolve voucher rules.
     */
    protected function resolveVoucher(string $code, Collection $cartItems, float $shippingFee, float $subtotal): array
    {
        $codes = array_filter(array_map('trim', explode(',', $code)));
        $promotions = Promotion::whereIn('code', $codes)
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        if ($promotions->isEmpty()) {
            return ['valid' => false, 'message' => 'Kode voucher tidak valid atau sudah kedaluwarsa.'];
        }

        $totalDiscountAmount = 0;
        $totalShippingDiscount = 0;
        $resolvedPromotions = [];
        $appliedDiscountType = null;
        $appliedDiscountValue = null;

        foreach ($promotions as $promo) {
            if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit) {
                return ['valid' => false, 'message' => "Voucher {$promo->code} sudah mencapai batas penggunaan."];
            }

            if ($promo->min_purchase && $subtotal < (float) $promo->min_purchase) {
                return ['valid' => false, 'message' => "Total belanja belum mencapai minimum untuk voucher {$promo->code} (Rp ".number_format($promo->min_purchase, 0, ',', '.').').'];
            }

            $discountType = $promo->discount_type;
            $discountValue = (float) $promo->discount_value;
            $maxDiscount = $promo->max_discount ? (float) $promo->max_discount : null;

            if ($promo->type === 'voucher_gratis_ongkir') {
                $shipDisc = $discountType === 'percentage'
                    ? round(($discountValue / 100) * $shippingFee, 2)
                    : min($discountValue, $shippingFee);

                if ($maxDiscount) {
                    $shipDisc = min($shipDisc, $maxDiscount);
                }
                $totalShippingDiscount += min($shipDisc, $shippingFee);
            } else {
                $disc = $discountType === 'percentage'
                    ? round(($discountValue / 100) * $subtotal, 2)
                    : min($discountValue, $subtotal);

                if ($maxDiscount) {
                    $disc = min($disc, $maxDiscount);
                }
                $totalDiscountAmount += min($disc, $subtotal);
                $appliedDiscountType = $discountType;
                $appliedDiscountValue = $discountValue;
            }

            $resolvedPromotions[] = $promo;
        }

        return [
            'valid' => true,
            'message' => 'Voucher berhasil diterapkan.',
            'promotion' => count($resolvedPromotions) === 1 ? $resolvedPromotions[0] : collect($resolvedPromotions),
            'discount_type' => $appliedDiscountType,
            'discount_value' => $appliedDiscountValue,
            'discount_amount' => $totalDiscountAmount,
            'shipping_discount' => $totalShippingDiscount,
        ];
    }
}
