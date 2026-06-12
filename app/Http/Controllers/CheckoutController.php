<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmation;
use App\Models\CartItem;
use App\Models\CoinHistory;
use App\Models\Courier;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Services\BiteshipService;
use App\Services\KomerceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    /**
     * Display the checkout page.
     */
    public function index(Request $request)
    {
        // Sync Komerce payment methods to ensure they reflect current setting status and admin fees
        KomerceService::syncPaymentMethods();

        $user = $request->user();

        $cartItems = $this->getCheckoutItems($user);

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Pilih produk terlebih dahulu sebelum checkout.');
        }

        // Load all active non-voucher promotions (includes bundling_gift)
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereNotIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
            ->where(function ($q) {
                $q->whereNull('code')
                    ->orWhere('code', '')
                    ->orWhere('type', '!=', 'promo_toko');
            })
            ->get();

        // Populate product info for bundling_gift promotions (same as CartController)
        $activePromotions->each(function ($promo) {
            if ($promo->type === 'bundling_gift' && isset($promo->settings['bundle'])) {
                $bundle = $promo->settings['bundle'];

                if (isset($bundle['buy_items'])) {
                    foreach ($bundle['buy_items'] as &$buyItem) {
                        if (! empty($buyItem['product_id'])) {
                            $prod = Product::with(['productPrice', 'images'])->find($buyItem['product_id']);
                            if ($prod) {
                                $buyItem['product_name'] = $prod->name;
                                $buyItem['product_slug'] = $prod->slug;
                                $buyItem['product_image'] = $prod->images->first()?->url ?? $prod->images->first()?->path ?? $prod->image;
                                $buyItem['product_price'] = (float) ($prod->productPrice?->price ?? 0);
                            }
                        }
                    }
                }

                if (isset($bundle['get_items'])) {
                    foreach ($bundle['get_items'] as &$getItem) {
                        if (! empty($getItem['product_id'])) {
                            $prod = Product::with(['productPrice', 'images'])->find($getItem['product_id']);
                            if ($prod) {
                                $getItem['product_name'] = $prod->name;
                                $getItem['product_slug'] = $prod->slug;
                                $getItem['product_image'] = $prod->images->first()?->url ?? $prod->images->first()?->path ?? $prod->image;
                                $getItem['product_price'] = (float) ($prod->productPrice?->price ?? 0);
                            }
                        }
                    }
                }

                $promo->settings = array_merge($promo->settings, ['bundle' => $bundle]);
            }
        });

        foreach ($cartItems as $item) {
            $product = $item->product;
            $variant = $item->productVariant;

            // Apply promotions
            $this->applyPromotionsToProduct($product, $activePromotions, $item->quantity);

            if ($variant) {
                $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                if ($matchedVariant) {
                    $item->setRelation('productVariant', $matchedVariant);
                    $item->setRelation('product_variant', $matchedVariant);
                    $variant = $matchedVariant;
                }
            }

            $basePrice = $variant
                ? (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0))
                : (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));

            $item->unit_price = $basePrice;
            $item->subtotal = $basePrice * $item->quantity;
        }

        $cartPromoPrices = session('cart_promo_prices', []);
        foreach ($cartItems as $item) {
            $key = $item->product_variant_id ? "v_{$item->product_variant_id}" : "p_{$item->product_id}";
            $expectedPromo = $cartPromoPrices[$key]['is_promo'] ?? false;
            $currentPromo = $item->productVariant ? ($item->productVariant->is_promo ?? false) : ($item->product->is_promo ?? false);

            if ($expectedPromo && ! $currentPromo) {
                session()->forget('cart_promo_prices');

                return redirect()->route('cart.index')->with('error', "Promo untuk produk {$item->product->name} telah habis. Harga kembali normal.");
            }
        }

        // Save to checkout_promo_prices
        $checkoutPromoPrices = [];
        foreach ($cartItems as $item) {
            $key = $item->product_variant_id ? "v_{$item->product_variant_id}" : "p_{$item->product_id}";
            $checkoutPromoPrices[$key] = [
                'price' => (float) $item->unit_price,
                'is_promo' => (bool) ($item->productVariant ? ($item->productVariant->is_promo ?? false) : ($item->product->is_promo ?? false)),
            ];
        }
        session(['checkout_promo_prices' => $checkoutPromoPrices]);

        // Addresses
        $addresses = CustomerAddress::where('user_id', $user->id)
            ->orderByDesc('is_primary')
            ->get();

        // Payment methods
        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Store settings
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');
        // RajaOngkir origin city: use stored regency_id as city ID for rajaongkir
        $storeOriginCity = Setting::where('key', 'rajaongkir_origin')->value('value')
            ?? Setting::where('key', 'regency_id')->value('value');
        $appFee = (float) (Setting::where('key', 'shipping_rate')->value('value') ?? 0);

        $vouchers = Promotion::where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->where(function ($q) {
                $q->whereIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
                    ->orWhere(function ($sq) {
                        $sq->where('type', 'promo_toko')
                            ->whereNotNull('code')
                            ->where('code', '!=', '');
                    });
            })
            ->get();

        $voucherCode = $request->query('voucher');
        if (! $voucherCode) {
            $voucherCode = Cache::get("user_{$user->id}_selected_vouchers");
        }
        $appliedVoucher = null;
        if ($voucherCode) {
            $result = $this->resolveVoucher($voucherCode, $cartItems, 0.0);
            if ($result['valid']) {
                $appliedVoucher = $result;
            }
        }

        $couriers = Courier::where('is_active', true)->orderBy('order')->get();

        return Inertia::render('Storefront/Checkout', [
            'cartItems' => $cartItems,
            'addresses' => $addresses,
            'paymentMethods' => $paymentMethods,
            'activePromotions' => $activePromotions,
            'vouchers' => $vouchers,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'storeOriginCity' => $storeOriginCity,
            'appFee' => $appFee,
            'appliedVoucher' => $appliedVoucher,
            'couriers' => $couriers,
        ]);
    }

    /**
     * Process the checkout and create a transaction.
     */
    public function store(Request $request)
    {
        // Guard: CHECKOUT_LOCKED in .env disables all new orders
        if (config('app.checkout_locked')) {
            $message = config('app.checkout_locked_message', 'Checkout sedang dinonaktifkan sementara. Silakan coba lagi nanti.');

            return back()->with('error', $message);
        }

        $user = $request->user();

        // Get checked cart items
        $cartItems = $this->getCheckoutItems($user);

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Tidak ada produk yang dipilih untuk checkout.');
        }

        $isDigitalOnly = $cartItems->every(fn ($item) => $item->product->is_digital);
        $isSelfPickup = $request->input('shipping_courier') === 'self_pickup';

        $rules = [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'courier_id' => 'nullable|exists:couriers,id',
            'shipping_courier' => 'required|string|max:50',
            'shipping_service' => 'required|string|max:50',
            'shipping_etd' => 'nullable|string|max:50',
            'shipping_fee' => 'required|numeric|min:0',
            'voucher_code' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'use_coins' => 'nullable|boolean',
            'item_notes' => 'nullable|array',
        ];

        if (! $isDigitalOnly && ! $isSelfPickup) {
            $rules['customer_address_id'] = 'required|exists:customer_addresses,id';
        } else {
            $rules['customer_address_id'] = 'nullable|exists:customer_addresses,id';
        }

        $request->validate($rules);

        // Validate address belongs to user if provided
        $address = null;
        if ($request->customer_address_id) {
            $address = CustomerAddress::where('user_id', $user->id)
                ->findOrFail($request->customer_address_id);
        }

        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

        // Validate COD compatibility with the chosen courier (per Komerce 3PL specs)
        $isCod = str_contains(strtolower($paymentMethod->name), 'cod');
        $courierLower = strtolower($request->shipping_courier);
        if ($isCod && in_array($courierLower, ['gosend', 'gojek', 'grab', 'grabexpress', 'lion'])) {
            return back()->with('error', 'Metode pembayaran Cash on Delivery (COD) tidak didukung oleh kurir '.$request->shipping_courier.'.');
        }

        // Get checked cart items
        $cartItems = $this->getCheckoutItems($user);

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Tidak ada produk yang dipilih untuk checkout.');
        }

        // Apply active promotions
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereNotIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
            ->where(function ($q) {
                $q->whereNull('code')
                    ->orWhere('code', '')
                    ->orWhere('type', '!=', 'promo_toko');
            })
            ->get();

        foreach ($cartItems as $item) {
            $this->applyPromotionsToProduct($item->product, $activePromotions, $item->quantity);
        }

        $checkoutPromoPrices = session('checkout_promo_prices', []);
        foreach ($cartItems as $item) {
            $variant = $item->productVariant;
            $product = $item->product;
            if ($variant) {
                $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                if ($matchedVariant) {
                    $variant = $matchedVariant;
                    $item->setRelation('productVariant', $matchedVariant);
                    $item->setRelation('product_variant', $matchedVariant);
                }
            }

            $key = $item->product_variant_id ? "v_{$item->product_variant_id}" : "p_{$item->product_id}";
            $expectedPromo = $checkoutPromoPrices[$key]['is_promo'] ?? false;
            $currentPromo = $variant ? ($variant->is_promo ?? false) : ($product->is_promo ?? false);

            if ($expectedPromo && ! $currentPromo) {
                session()->forget('checkout_promo_prices');

                return redirect()->route('cart.index')->with('error', "Promo untuk produk {$product->name} telah habis. Harga kembali normal.");
            }
        }

        // Validate physical stock availability before processing checkout
        foreach ($cartItems as $item) {
            $variant = $item->productVariant;
            $product = $item->product;

            if ($variant) {
                $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                if ($matchedVariant) {
                    $variant = $matchedVariant;
                }
            }

            $stockRecord = $variant
                ? $variant->productStock
                : $product->productStock;

            if ($stockRecord && ! $stockRecord->is_unlimited) {
                if ($stockRecord->stock <= 0) {
                    return redirect()->route('cart.index')->with('error', "Stok produk {$product->name} sudah habis. Silakan perbarui keranjang Anda.");
                }

                if ($stockRecord->stock < $item->quantity) {
                    return redirect()->route('cart.index')->with('error', "Stok produk {$product->name} tidak mencukupi. Tersisa {$stockRecord->stock} pcs, Anda memesan {$item->quantity} pcs.");
                }
            }
        }

        // Process voucher
        $voucherCode = $request->voucher_code;
        $voucherDiscountType = null;
        $voucherDiscountValue = null;
        $voucherPromotion = null;
        $discountAmount = 0;
        $shippingDiscount = 0;

        if ($voucherCode) {
            $result = $this->resolveVoucher($voucherCode, $cartItems, $request->shipping_fee);

            if (! $result['valid']) {
                return back()->with('error', $result['message']);
            }

            $voucherPromotion = $result['promotion'];
            $voucherDiscountType = $result['discount_type'];
            $voucherDiscountValue = $result['discount_value'];
            $discountAmount = $result['discount_amount'];
            $shippingDiscount = $result['shipping_discount'];
        }

        // Calculate subtotal
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $variant = $item->productVariant;
            $product = $item->product;

            $basePrice = $variant
                ? (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0))
                : (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));

            $subtotal += $basePrice * $item->quantity;
        }

        $shippingFee = (float) $request->shipping_fee;
        $adminFee = (float) ($paymentMethod->admin_fee ?? 0);
        $appFee = (float) (Setting::where('key', 'shipping_rate')->value('value') ?? 0);
        $grandTotal = $subtotal - $discountAmount + ($shippingFee - $shippingDiscount) + $adminFee + $appFee;
        $grandTotal = max(0, $grandTotal);

        // --- Loyalty Coins Redemption Logic ---
        $coinsRedeemed = 0;
        $coinsValue = 0;
        $coinsEnabled = Setting::where('key', 'coins_enabled')->value('value') === '1';

        if ($coinsEnabled && $request->boolean('use_coins') && $user->coins_balance > 0) {
            $coinConversionRate = (float) (Setting::where('key', 'coin_conversion_rate')->value('value') ?? 1);
            $coinMinPurchaseRedeem = (float) (Setting::where('key', 'coin_min_purchase_redeem')->value('value') ?? 0);
            $coinMaxRedeemPerTxn = (float) (Setting::where('key', 'coin_max_redeem_per_txn')->value('value') ?? 50000);
            $coinMaxRedeemPercentage = (float) (Setting::where('key', 'coin_max_redeem_percentage')->value('value') ?? 100);

            if ($subtotal >= $coinMinPurchaseRedeem) {
                // Calculate max coins by percentage of subtotal
                $maxCoinsByPercentage = ($subtotal * ($coinMaxRedeemPercentage / 100)) / $coinConversionRate;

                // Max redeemable coins is the minimum of balance, transaction cap, and percentage cap
                $maxRedeemableCoins = min($user->coins_balance, $coinMaxRedeemPerTxn, $maxCoinsByPercentage);

                // Discount cannot exceed the grand total before coins
                $maxDiscountValue = min($maxRedeemableCoins * $coinConversionRate, $grandTotal);

                if ($maxDiscountValue > 0) {
                    $coinsRedeemed = (int) floor($maxDiscountValue / $coinConversionRate);
                    $coinsValue = $coinsRedeemed * $coinConversionRate;
                    $grandTotal = max(0, $grandTotal - $coinsValue);
                }
            }
        }

        // --- Loyalty Coins Earning Calculation ---
        $coinsEarned = 0;
        if ($coinsEnabled && $coinsRedeemed <= 0) {
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
                    // Sort descending by min_purchase
                    usort($coinEarningTiers, function ($a, $b) {
                        return ($b['min_purchase'] ?? 0) <=> ($a['min_purchase'] ?? 0);
                    });

                    foreach ($coinEarningTiers as $tier) {
                        $minPurchase = (float) ($tier['min_purchase'] ?? 0);
                        $earnCoins = (int) ($tier['earn_coins'] ?? 0);

                        if ($subtotal >= $minPurchase) {
                            $coinsEarned = $earnCoins;
                            break; // Got the highest tier
                        }
                    }
                }
            }
        }

        DB::transaction(function () use (
            $user,
            $address,
            $paymentMethod,
            $cartItems,
            $request,
            $subtotal,
            $discountAmount,
            $shippingFee,
            $shippingDiscount,
            $adminFee,
            $appFee,
            $grandTotal,
            $voucherCode,
            $voucherDiscountType,
            $voucherDiscountValue,
            $voucherPromotion,
            $coinsRedeemed,
            $coinsValue,
            $coinsEarned
        ) {
            // Create transaction
            $transaction = Transaction::create([
                'transaction_number' => Transaction::generateNumber(),
                'user_id' => $user->id,
                'customer_address_id' => $address?->id,
                'payment_method_id' => $paymentMethod->id,
                'courier_id' => $request->courier_id,
                'status' => 'belum_bayar',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_fee' => $shippingFee,
                'shipping_discount' => $shippingDiscount,
                'admin_fee' => $adminFee,
                'application_fee' => $appFee,
                'grand_total' => $grandTotal,
                'shipping_courier' => $request->shipping_courier,
                'shipping_service' => $request->shipping_service,
                'shipping_etd' => $request->shipping_etd,
                'voucher_code' => $voucherCode,
                'voucher_discount_type' => $voucherDiscountType,
                'voucher_discount_value' => $voucherDiscountValue,
                'notes' => $request->notes,
                'coins_redeemed' => $coinsRedeemed,
                'coins_value' => $coinsValue,
                'coins_earned' => $coinsEarned,
            ]);

            // Deduct user coins immediately
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

            // Log status history
            $transaction->statusHistories()->create([
                'status' => 'belum_bayar',
                'description' => 'Pesanan berhasil dibuat, menunggu pembayaran.',
                'created_by' => $user->id,
            ]);

            // Create transaction items & reduce stock
            foreach ($cartItems as $item) {
                $product = $item->product;
                $variant = $item->productVariant;

                // Get priced fields
                if ($variant) {
                    $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                    if ($matchedVariant) {
                        $variant = $matchedVariant;
                    }

                    $hargaJual = (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0));
                    $hpp = (float) ($variant->productPrice?->cost ?? 0);
                    $variantName = $variant->options->pluck('name')->join(' / ');
                    $sku = $variant->sku;
                } else {
                    $hargaJual = (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));
                    $hpp = (float) ($product->productPrice?->cost ?? 0);
                    $variantName = null;
                    $sku = $product->sku;
                }

                $diskonItem = 0;
                $hargaAkhir = $hargaJual - $diskonItem;
                $itemSubtotal = $hargaAkhir * $item->quantity;

                // Get product image
                $productImage = $product->images->first()?->path ?? $product->image;

                $appliedPromotionId = $variant ? ($variant->applied_promotion_id ?? null) : ($product->applied_promotion_id ?? null);
                $promoQtyUsed = $variant ? ($variant->promo_quantity_used ?? null) : ($product->promo_quantity_used ?? null);

                $itemNotes = $request->input('item_notes', []);
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

                // Reduce stock
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

            // Create transaction payment (pending)
            $paymentData = [
                'transaction_id' => $transaction->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $grandTotal,
                'status' => 'pending',
            ];

            // If it's gateway, detect whether it is Komerce, Xendit, Midtrans or Flip
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
                            $errorMsg = $response['error'] ?? 'Gagal generate pembayaran QRIS Komerce.';
                            Log::error('Komerce QRIS Creation Failed: '.$errorMsg);
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $errorMsg]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Komerce QRIS Exception: '.$e->getMessage());
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
                            $errorMsg = $response['error'] ?? 'Gagal generate URL Komerce Payment.';
                            Log::error('Komerce Payment URL Creation Failed: '.$errorMsg);
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $errorMsg]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Komerce Payment URL Exception: '.$e->getMessage());
                        $paymentData['gateway_status'] = 'FAILED_API';
                        $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                    }
                } else {
                    $isMidtrans = str_contains($methodName, 'midtrans');

                    if ($isMidtrans) {
                        try {
                            $serverKey = $paymentMethod->api_key ?: config('app.midtrans.server_key');

                            $baseUrl = ($paymentMethod->settings && isset($paymentMethod->settings['url']))
                                ? $paymentMethod->settings['url']
                                : config('app.midtrans.snap_url', 'https://app.sandbox.midtrans.com');

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

                            $response = Http::withBasicAuth($serverKey, '')
                                ->timeout(15)
                                ->post($midtransUrl, $payload);

                            if ($response->successful()) {
                                $responseData = $response->json();
                                $paymentData['gateway_transaction_id'] = $responseData['token'] ?? null;
                                $paymentData['gateway_status'] = 'PENDING';
                                $paymentData['gateway_response'] = json_encode($responseData);
                            } else {
                                $errorMsg = $response->json('error_messages')[0] ?? 'Gagal menghubungi Midtrans Snap.';
                                Log::error('Midtrans Snap Creation Failed: '.$errorMsg);
                                $paymentData['gateway_status'] = 'FAILED_API';
                                $paymentData['gateway_response'] = json_encode(['error' => $errorMsg]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Midtrans Connection Exception: '.$e->getMessage());
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                        }
                    } elseif (str_contains($methodName, 'flip')) {
                        try {
                            $secretKey = $paymentMethod->api_key ?: config('app.flip.secret_key');

                            $baseUrl = ($paymentMethod->settings && isset($paymentMethod->settings['url']))
                                ? $paymentMethod->settings['url']
                                : config('app.flip.base_url', 'https://bigflip.id/big_sandbox_api');

                            $flipUrl = rtrim($baseUrl, '/').'/v2/pwf/bill';

                            $redirectUrl = env('FLIP_REDIRECT_URL') ?: route('transactions.show', $transaction->id);

                            // If redirectUrl still has a transaction id placeholder, append it if needed
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

                            $response = Http::withBasicAuth($secretKey, '')
                                ->timeout(15)
                                ->asForm()
                                ->post($flipUrl, $payload);

                            if ($response->successful()) {
                                $responseData = $response->json();
                                $paymentData['gateway_transaction_id'] = $responseData['link_id'] ?? null;
                                $paymentData['gateway_status'] = $responseData['status'] ?? 'ACTIVE';
                                $paymentData['gateway_response'] = json_encode($responseData);
                            } else {
                                $errorMsg = $response->json('message') ?? 'Gagal menghubungi Flip API.';
                                Log::error('Flip Bill Creation Failed: status='.$response->status().' body='.$response->body());
                                $paymentData['gateway_status'] = 'FAILED_API';
                                $paymentData['gateway_response'] = json_encode(['error' => $errorMsg, 'response_body' => $response->body()]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Flip Connection Exception: '.$e->getMessage());
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                        }
                    } else {
                        try {
                            $secretKey = $paymentMethod->api_secret ?: config('app.xendit.private_key');

                            $baseUrl = ($paymentMethod->settings && isset($paymentMethod->settings['url']))
                                ? $paymentMethod->settings['url']
                                : config('app.xendit.url', 'https://api.xendit.co');

                            $xenditUrl = rtrim($baseUrl, '/').'/v2/invoices';

                            $payload = [
                                'external_id' => $transaction->transaction_number,
                                'amount' => (float) $grandTotal,
                                'payer_email' => $user->email,
                                'description' => 'Pembayaran Pesanan #'.$transaction->transaction_number.' di '.config('app.name'),
                                'success_redirect_url' => route('transactions.show', $transaction->id),
                                'failure_redirect_url' => route('transactions.show', $transaction->id),
                            ];

                            $response = Http::withBasicAuth($secretKey, '')
                                ->timeout(15)
                                ->post($xenditUrl, $payload);

                            if ($response->successful()) {
                                $responseData = $response->json();
                                $paymentData['gateway_transaction_id'] = $responseData['id'] ?? null;
                                $paymentData['gateway_status'] = $responseData['status'] ?? 'PENDING';
                                $paymentData['gateway_response'] = json_encode($responseData);
                            } else {
                                $errorMsg = $response->json('message') ?? 'Terjadi kesalahan saat menghubungkan ke Xendit.';
                                Log::error('Xendit Invoice Creation Failed: '.$errorMsg);
                                $paymentData['gateway_status'] = 'FAILED_API';
                                $paymentData['gateway_response'] = json_encode(['error' => $errorMsg]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Xendit Connection Exception: '.$e->getMessage());
                            $paymentData['gateway_status'] = 'FAILED_API';
                            $paymentData['gateway_response'] = json_encode(['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            TransactionPayment::create($paymentData);

            // Update voucher used_count
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
                // Remove checked cart items
                CartItem::where('user_id', $user->id)
                    ->where('is_checked', true)
                    ->delete();
            }

            // Store transaction number in session for redirect
            session(['last_transaction_number' => $transaction->transaction_number]);

            return $transaction;
        });

        $transactionNumber = session('last_transaction_number');
        $transaction = Transaction::where('transaction_number', $transactionNumber)->first();

        // Send order confirmation email (fail silently so it never blocks the redirect)
        try {
            $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
            $storeLogo = Setting::where('key', 'store_logo')->value('value');

            $transaction->load(['items', 'user', 'customerAddress', 'paymentMethod']);

            Mail::to($user->email)->queue(new OrderConfirmation($transaction, $storeName, $storeLogo));
        } catch (\Throwable $e) {
            Log::error('Order confirmation email failed for transaction '.$transaction->transaction_number.': '.$e->getMessage());
        }

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Pesanan berhasil dibuat!');
    }

    /**
     * Apply voucher code to get discount details.
     *
     * @param  Collection  $cartItems
     * @return array{valid: bool, message: string, promotion: ?Promotion, discount_type: ?string, discount_value: float, discount_amount: float, shipping_discount: float}
     */
    public function applyVoucher(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            'shipping_fee' => 'required|numeric|min:0',
        ]);

        $user = $request->user();

        $cartItems = CartItem::with([
            'product.productPrice',
            'product.productStock',
            'product.variants.productPrice',
            'product.variants.productStock',
            'productVariant.productPrice',
            'productVariant.productStock',
        ])
            ->where('user_id', $user->id)
            ->where('is_checked', true)
            ->get();

        // Load active promotions to dynamically determine is_promo and promo pricing
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereNotIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
            ->where(function ($q) {
                $q->whereNull('code')
                    ->orWhere('code', '')
                    ->orWhere('type', '!=', 'promo_toko');
            })
            ->get();

        foreach ($cartItems as $item) {
            $product = $item->product;
            $variant = $item->productVariant;

            // Apply promotions first
            $this->applyPromotionsToProduct($product, $activePromotions, $item->quantity);

            if ($variant) {
                $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                if ($matchedVariant) {
                    $item->setRelation('productVariant', $matchedVariant);
                    $variant = $matchedVariant;
                }
            }

            $basePrice = $variant
                ? (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0))
                : (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));

            $item->unit_price = $basePrice;
            $item->subtotal = $basePrice * $item->quantity;
        }

        $result = $this->resolveVoucher($request->code, $cartItems, $request->shipping_fee, $request->subtotal);

        return response()->json($result);
    }

    /**
     * Upload payment proof for a transaction.
     */
    public function uploadProof(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'proof_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = $request->file('proof_image')->store('payments/proofs', 'public');

        $payment = $transaction->payment;
        if (! $payment) {
            $payment = $transaction->payments()->create([
                'payment_method_id' => $transaction->payment_method_id,
                'amount' => $transaction->grand_total,
                'status' => 'pending',
            ]);
        }

        // Delete old proof image if exists
        if ($payment->proof_image) {
            Storage::disk('public')->delete($payment->proof_image);
        }

        $payment->update([
            'proof_image' => $path,
            'proof_uploaded_at' => now(),
            'status' => 'pending',
        ]);

        // Update transaction status to 'menunggu'
        if ($transaction->status === 'belum_bayar') {
            $transaction->update(['status' => 'menunggu']);
        }

        return back()->with('success', 'Bukti pembayaran berhasil diunggah!');
    }

    /**
     * Proxy RajaOngkir API to get shipping cost options.
     */
    public function shippingCost(Request $request)
    {
        $request->validate([
            'destination' => 'required_without:address_id|nullable|string',
            'weight' => 'required|integer|min:1',
            'courier' => 'required|string',
            'is_international' => 'nullable|boolean',
            'address_id' => 'nullable|string',
        ]);

        if ($request->courier === 'self_pickup') {
            $enabled = Setting::where('key', 'self_pickup_enabled')->value('value') === '1';
            if (! $enabled) {
                return response()->json(['error' => 'Metode Ambil di Toko tidak aktif.'], 422);
            }
            $fee = (float) (Setting::where('key', 'self_pickup_fee')->value('value') ?? 0);

            return response()->json([
                'results' => [
                    [
                        'code' => 'self_pickup',
                        'name' => 'Ambil di Toko',
                        'costs' => [
                            [
                                'service' => 'Self Pickup',
                                'description' => 'Ambil langsung di toko / outlet',
                                'cost' => [
                                    [
                                        'value' => $fee,
                                        'etd' => '0',
                                        'note' => 'Silakan ambil di alamat toko kami.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        if ($request->courier === 'store_courier') {
            $enabled = Setting::where('key', 'store_courier_enabled')->value('value') === '1';
            if (! $enabled) {
                return response()->json(['error' => 'Metode Kurir Toko tidak aktif.'], 422);
            }

            $customerAddress = CustomerAddress::find($request->address_id);
            if (! $customerAddress) {
                return response()->json(['error' => 'Alamat pengiriman tidak ditemukan.'], 422);
            }

            $storeLat = Setting::where('key', 'latitude')->value('value');
            $storeLng = Setting::where('key', 'longitude')->value('value');

            if (! $storeLat || ! $storeLng) {
                return response()->json(['error' => 'Koordinat toko (latitude/longitude) belum diatur di pengaturan.'], 422);
            }

            if (! $customerAddress->latitude || ! $customerAddress->longitude) {
                return response()->json(['error' => 'Koordinat alamat pengiriman Anda belum diatur. Silakan edit alamat untuk menentukan pin point pada peta.'], 422);
            }

            // Haversine distance
            $earthRadius = 6371; // km
            $latDelta = deg2rad((float) $customerAddress->latitude - (float) $storeLat);
            $lonDelta = deg2rad((float) $customerAddress->longitude - (float) $storeLng);
            $a = sin($latDelta / 2) * sin($latDelta / 2) +
                cos(deg2rad((float) $storeLat)) * cos(deg2rad((float) $customerAddress->latitude)) *
                sin($lonDelta / 2) * sin($lonDelta / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = $earthRadius * $c; // in km

            $maxRadius = (float) (Setting::where('key', 'store_courier_max_radius')->value('value') ?? 50);
            if ($distance > $maxRadius) {
                return response()->json(['error' => 'Jarak pengiriman melebihi radius maksimal Kurir Toko (maksimal '.$maxRadius.' km). Jarak saat ini: '.round($distance, 1).' km.'], 422);
            }

            // Check if distance should be rounded up to the nearest kilometer
            $roundUp = Setting::where('key', 'store_courier_round_up')->value('value') === '1';
            if ($roundUp) {
                $distance = ceil($distance);
                if ($distance < 1) {
                    $distance = 1;
                }
            }

            $type = Setting::where('key', 'store_courier_type')->value('value') ?? 'flat';
            if ($type === 'radius') {
                $perKmFee = (float) (Setting::where('key', 'store_courier_per_km_fee')->value('value') ?? 0);
                $fee = round($perKmFee * $distance);
            } elseif ($type === 'radius_tiered') {
                $tieredRatesVal = Setting::where('key', 'store_courier_tiered_rates')->value('value');
                $tieredRates = $tieredRatesVal ? json_decode($tieredRatesVal, true) : [];
                if (! is_array($tieredRates)) {
                    $tieredRates = [];
                }

                // Sort tiers by max_distance ascending
                usort($tieredRates, function ($a, $b) {
                    $distA = (float) ($a['max_distance'] ?? 0);
                    $distB = (float) ($b['max_distance'] ?? 0);

                    return $distA <=> $distB;
                });

                $fee = null;
                foreach ($tieredRates as $tier) {
                    if ($distance <= (float) ($tier['max_distance'] ?? 0)) {
                        $fee = (float) ($tier['fee'] ?? 0);
                        break;
                    }
                }

                if ($fee === null) {
                    // Fallback to the highest tier's fee if distance exceeds all tiers
                    if (! empty($tieredRates)) {
                        $lastTier = end($tieredRates);
                        $fee = (float) ($lastTier['fee'] ?? 0);
                    } else {
                        $fee = 0.0;
                    }
                }
            } else {
                $fee = (float) (Setting::where('key', 'store_courier_flat_fee')->value('value') ?? 0);
            }

            $description = match ($type) {
                'flat' => 'Tarif Flat (Sama Rata)',
                'radius' => 'Tarif Berdasarkan Radius (per Km)',
                'radius_tiered' => 'Tarif Berdasarkan Radius Bertingkat (Tiered)',
                default => 'Dikirim menggunakan kurir internal toko',
            };

            return response()->json([
                'results' => [
                    [
                        'code' => 'store_courier',
                        'name' => 'Kurir Toko',
                        'costs' => [
                            [
                                'service' => 'Store Courier',
                                'description' => $description,
                                'cost' => [
                                    [
                                        'value' => $fee,
                                        'etd' => '1-2 hari',
                                        'note' => 'Jarak pengiriman: '.round($distance, 1).' km.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        $origin = Setting::where('key', 'rajaongkir_origin')->value('value')
            ?? Setting::where('key', 'regency_id')->value('value');

        if (! $origin) {
            return response()->json(['error' => 'Konfigurasi kota asal belum diatur di pengaturan.'], 422);
        }

        $originName = Setting::where('key', 'regency_name')->value('value') ?? 'Unknown Origin';
        $customerAddress = $request->address_id ? CustomerAddress::find($request->address_id) : null;

        Log::info('Shipping cost calculation request payload:', [
            'origin_id' => $origin,
            'origin_name' => $originName,
            'destination_id' => $request->destination,
            'destination_name' => $customerAddress ? $customerAddress->regency_name : 'Unknown Destination',
            'customer_address_detail' => $customerAddress ? $customerAddress->full_address : null,
            'weight' => $request->weight,
            'courier' => $request->courier,
            'is_international' => $request->is_international,
            'address_id' => $request->address_id,
        ]);

        if ($request->is_international) {
            $response = KomerceService::getInternationalCost(
                $origin,
                (string) ($request->destination ?? ''),
                $request->weight,
                $request->courier
            );
        } elseif (KomerceService::isDeliveryEnabled()) {
            $response = KomerceService::getDomesticCost(
                $origin,
                (string) ($request->destination ?? ''),
                $request->weight,
                $request->courier,
                $request->address_id
            );
        } elseif (BiteshipService::isEnabled()) {
            $user = $request->user();
            $cartItems = $user ? $this->getCheckoutItems($user) : collect();
            $response = BiteshipService::getDomesticCost(
                $origin,
                (string) ($request->destination ?? ''),
                $request->weight,
                $request->courier,
                $request->address_id,
                $cartItems
            );
        } else {
            $response = KomerceService::getDomesticCost(
                $origin,
                (string) ($request->destination ?? ''),
                $request->weight,
                $request->courier,
                $request->address_id
            );
        }

        Log::info('Shipping cost calculation response:', [
            'response' => $response,
        ]);

        if (isset($response['error'])) {
            return response()->json(['error' => $response['error']], 422);
        }

        return response()->json(['results' => $response['results'] ?? []]);
    }

    /**
     * Search destination in Komerce Collaborator API.
     */
    public function searchKomerceDestination(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|min:2',
        ]);

        if (KomerceService::isDeliveryEnabled()) {
            $response = KomerceService::searchDestination($request->keyword);
        } elseif (BiteshipService::isEnabled()) {
            $response = BiteshipService::searchDestination($request->keyword);
        } else {
            $response = KomerceService::searchDestination($request->keyword);
        }

        if (isset($response['error'])) {
            return response()->json(['error' => $response['error']], 422);
        }

        return response()->json(['data' => $response['data'] ?? []]);
    }

    /**
     * Proxy RajaOngkir API to get international country list.
     */
    public function internationalDestinations(Request $request)
    {
        $destinations = KomerceService::getInternationalDestinations();

        return response()->json(['destinations' => $destinations]);
    }

    /**
     * Proxy RajaOngkir API to get city list.
     */
    public function cities(Request $request)
    {
        $apiKey = Setting::where('key', 'rajaongkir_shipping_cost')->value('value')
            ?? config('app.rajaongkir.shipping_cost');
        $baseUrl = Setting::where('key', 'rajaongkir_url')->value('value')
            ?? config('app.rajaongkir.url', 'https://rajaongkir.komerce.id/api/v1/');

        if (! $apiKey) {
            return response()->json(['error' => 'Konfigurasi RajaOngkir belum diatur.'], 422);
        }

        try {
            $response = Http::withHeaders(['key' => $apiKey])
                ->get(rtrim($baseUrl, '/').'/city', [
                    'province' => $request->province_id,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $cities = $data['rajaongkir']['results'] ?? [];

                return response()->json(['cities' => $cities]);
            }

            return response()->json(['error' => 'Gagal mendapatkan data kota.'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal terhubung ke layanan pengiriman.'], 422);
        }
    }

    /**
     * Resolve a voucher code and calculate discounts.
     *
     * @param  Collection  $cartItems
     * @return array{valid: bool, message: string, promotion: ?Promotion, discount_type: ?string, discount_value: float, discount_amount: float, shipping_discount: float}
     */
    private function resolveVoucher(string $code, $cartItems, float $shippingFee, ?float $subtotal = null): array
    {
        $codes = array_filter(array_map(function ($c) {
            return trim(strtoupper($c));
        }, explode(',', $code)));

        if (empty($codes)) {
            return [
                'valid' => false,
                'message' => 'Kode voucher tidak boleh kosong.',
                'promotion' => null,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'shipping_discount' => 0,
            ];
        }

        $resolvedPromotions = [];
        $hasShippingVoucher = false;
        $hasDiscountVoucher = false;
        $promotionNames = [];

        // Calculate subtotal if not passed
        if ($subtotal === null) {
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $unitPrice = is_array($item) ? ($item['unit_price'] ?? 0) : ($item->unit_price ?? 0);
                $quantity = is_array($item) ? ($item['quantity'] ?? 1) : ($item->quantity ?? 1);
                $subtotal += $unitPrice * $quantity;
            }
        }

        $user = auth()->user();

        foreach ($codes as $c) {
            $promotion = Promotion::where('code', $c)
                ->where('is_active', true)
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->whereIn('type', ['voucher_gratis_ongkir', 'voucher_belanja', 'promo_toko'])
                ->first();

            if (! $promotion) {
                return [
                    'valid' => false,
                    'message' => "Kode voucher \"{$c}\" tidak valid atau sudah kadaluarsa.",
                    'promotion' => null,
                    'discount_type' => null,
                    'discount_value' => 0,
                    'discount_amount' => 0,
                    'shipping_discount' => 0,
                ];
            }

            // Check if this type is already added
            if ($promotion->type === 'voucher_gratis_ongkir') {
                if ($hasShippingVoucher) {
                    return [
                        'valid' => false,
                        'message' => 'Anda hanya dapat menggunakan satu voucher gratis ongkir.',
                        'promotion' => null,
                        'discount_type' => null,
                        'discount_value' => 0,
                        'discount_amount' => 0,
                        'shipping_discount' => 0,
                    ];
                }
                $hasShippingVoucher = true;
            } else {
                if ($hasDiscountVoucher) {
                    return [
                        'valid' => false,
                        'message' => 'Anda hanya dapat menggunakan satu voucher belanja / diskon toko.',
                        'promotion' => null,
                        'discount_type' => null,
                        'discount_value' => 0,
                        'discount_amount' => 0,
                        'shipping_discount' => 0,
                    ];
                }
                $hasDiscountVoucher = true;
            }

            // Check quota
            if ($promotion->quota !== null && $promotion->used_count >= $promotion->quota) {
                return [
                    'valid' => false,
                    'message' => "Kuota voucher \"{$c}\" telah habis.",
                    'promotion' => null,
                    'discount_type' => null,
                    'discount_value' => 0,
                    'discount_amount' => 0,
                    'shipping_discount' => 0,
                ];
            }

            // Check max uses per user
            $maxUsesPerUser = $promotion->settings['max_uses_per_user'] ?? null;
            if ($user && $maxUsesPerUser !== null && $maxUsesPerUser !== '') {
                $maxUses = (int) $maxUsesPerUser;
                $operator = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';
                $usedCount = Transaction::where('user_id', $user->id)
                    ->where(function ($q) use ($promotion, $operator) {
                        $q->where('voucher_code', $promotion->code)
                            ->orWhere('voucher_code', $operator, "%,{$promotion->code}")
                            ->orWhere('voucher_code', $operator, "{$promotion->code},%")
                            ->orWhere('voucher_code', $operator, "%,{$promotion->code},%");
                    })
                    ->count();

                if ($usedCount >= $maxUses) {
                    return [
                        'valid' => false,
                        'message' => "Batas penggunaan voucher \"{$promotion->code}\" per user telah tercapai.",
                        'promotion' => null,
                        'discount_type' => null,
                        'discount_value' => 0,
                        'discount_amount' => 0,
                        'shipping_discount' => 0,
                    ];
                }
            }

            // Check minimum purchase
            if ($promotion->min_purchase && $subtotal < $promotion->min_purchase) {
                return [
                    'valid' => false,
                    'message' => 'Minimum pembelian Rp '.number_format($promotion->min_purchase, 0, ',', '.')." untuk menggunakan voucher \"{$promotion->code}\".",
                    'promotion' => null,
                    'discount_type' => null,
                    'discount_value' => 0,
                    'discount_amount' => 0,
                    'shipping_discount' => 0,
                ];
            }

            $resolvedPromotions[] = $promotion;
            $promotionNames[] = $promotion->name;
        }

        $normalSubtotal = 0;
        foreach ($cartItems as $item) {
            $unitPrice = is_array($item) ? ($item['unit_price'] ?? 0) : ($item->unit_price ?? 0);
            $quantity = is_array($item) ? ($item['quantity'] ?? 1) : ($item->quantity ?? 1);

            $itemProduct = is_array($item) ? ($item['product'] ?? null) : ($item->product ?? null);
            $itemVariant = is_array($item) ? ($item['productVariant'] ?? $item['product_variant'] ?? null) : ($item->productVariant ?? null);

            $isOnPromo = false;
            if ($itemVariant) {
                $isOnPromo = is_array($itemVariant) ? ($itemVariant['is_promo'] ?? false) : ($itemVariant->is_promo ?? false);
            } elseif ($itemProduct) {
                $isOnPromo = is_array($itemProduct) ? ($itemProduct['is_promo'] ?? false) : ($itemProduct->is_promo ?? false);
            }

            if (! $isOnPromo) {
                $normalSubtotal += $unitPrice * $quantity;
            }
        }

        $totalDiscountAmount = 0;
        $totalShippingDiscount = 0;

        foreach ($resolvedPromotions as $promo) {
            if ($promo->type === 'voucher_gratis_ongkir') {
                $shippingDiscount = min($shippingFee, (float) ($promo->max_discount ?? $shippingFee));
                $totalShippingDiscount += $shippingDiscount;
            } else {
                $canStack = $promo->settings['can_stack_with_promos'] ?? true;
                $eligibleSubtotal = $canStack ? $subtotal : $normalSubtotal;

                $discountAmount = 0;
                if ($promo->discount_type === 'percentage') {
                    $discountAmount = $eligibleSubtotal * ($promo->discount_value / 100);
                } else {
                    $discountAmount = (float) $promo->discount_value;
                }

                $discountAmount = min($discountAmount, $eligibleSubtotal);

                if ($promo->max_discount) {
                    $discountAmount = min($discountAmount, (float) $promo->max_discount);
                }
                $totalDiscountAmount += $discountAmount;
            }
        }

        $singlePromo = count($resolvedPromotions) === 1 ? reset($resolvedPromotions) : null;

        return [
            'valid' => true,
            'message' => 'Voucher berhasil diterapkan!',
            'promotion' => count($resolvedPromotions) === 1 ? $singlePromo : $resolvedPromotions,
            'promotions' => $resolvedPromotions,
            'promotion_name' => implode(' + ', $promotionNames),
            'discount_type' => $singlePromo ? $singlePromo->discount_type : 'mixed',
            'discount_value' => $singlePromo ? (float) $singlePromo->discount_value : 0.0,
            'discount_amount' => round($totalDiscountAmount, 2),
            'shipping_discount' => round($totalShippingDiscount, 2),
        ];
    }

    /**
     * Apply active promotions to a product (reused from CartController).
     */
    /**
     * Helper to apply active promotions on a product and its variants (reused from CartController).
     */
    private function applyPromotionsToProduct($product, $activePromotions, int $quantity = 1): void
    {
        $basePrice = (float) ($product->productPrice?->price ?? 0);

        // Find if this product is in any active Flash Sale
        $flashSalePromo = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'flash_sale') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true; // Store-wide
            }

            return $promo->items->contains(function ($i) use ($product) {
                return $i->product_id === $product->id;
            });
        });

        // Find if this product is in any active Promo Bundling
        $isBundlingActive = $activePromotions->contains(function ($promo) use ($product) {
            if ($promo->type !== 'bundling_gift') {
                return false;
            }
            $bundle = $promo->settings['bundle'] ?? null;
            if (! $bundle || empty($bundle['buy_items'])) {
                return false;
            }
            foreach ($bundle['buy_items'] as $bi) {
                if ($bi['product_id'] == $product->id) {
                    return true;
                }
            }

            return false;
        });

        $appliedPromo = null;
        $appliedItem = null;

        // Clear out any previous promo rule
        $product->promo_rule = null;
        $product->applied_promotion_id = null;
        $product->promo_quantity_used = null;

        if ($flashSalePromo) {
            // Priority 1: Flash Sale
            $remainingStock = $this->getRemainingPromoStock($flashSalePromo->id, $product->id, null);
            if (! is_null($remainingStock)) {
                $product->promo_rule = [
                    'id' => $flashSalePromo->id,
                    'name' => $flashSalePromo->name,
                    'type' => $flashSalePromo->type,
                    'min_qty' => 1,
                    'remaining_promo_stock' => $remainingStock,
                ];
            }

            if (is_null($remainingStock) || $remainingStock > 0) {
                $appliedPromo = $flashSalePromo;
                if ($appliedPromo->items->isNotEmpty()) {
                    $appliedItem = $appliedPromo->items->first(function ($i) use ($product) {
                        return $i->product_id === $product->id && is_null($i->product_variant_id);
                    });
                }
            }
        } elseif ($isBundlingActive) {
            // Priority 2: Promo Bundling is active, so we skip Promo Produk entirely!
            $appliedPromo = null;
        } else {
            // Priority 3: Promo Produk (and other general store promos like promo_toko if any)
            $promoProduk = $activePromotions->first(function ($promo) use ($product) {
                if ($promo->type !== 'promo_produk') {
                    return false;
                }
                if ($promo->items->isEmpty()) {
                    return true;
                }

                return $promo->items->contains(function ($i) use ($product) {
                    return $i->product_id === $product->id;
                });
            });

            if ($promoProduk) {
                $minQty = $promoProduk->settings['min_qty'] ?? 1;

                $appliedItem = null;
                if ($promoProduk->items->isNotEmpty()) {
                    $appliedItem = $promoProduk->items->first(function ($i) use ($product) {
                        return $i->product_id === $product->id && is_null($i->product_variant_id);
                    });
                }

                $resolvedDiscountType = $appliedItem ? ($appliedItem->discount_type ?? $promoProduk->discount_type) : $promoProduk->discount_type;
                $resolvedDiscountValue = $appliedItem ? ($appliedItem->discount_value ?? $promoProduk->discount_value) : $promoProduk->discount_value;
                $resolvedPromoPrice = $appliedItem?->promo_price;

                $remainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, null);

                // Expose the promo rule regardless of min_qty so frontend can see it
                $product->promo_rule = [
                    'id' => $promoProduk->id,
                    'name' => $promoProduk->name,
                    'type' => $promoProduk->type,
                    'min_qty' => (int) $minQty,
                    'discount_type' => $resolvedDiscountType,
                    'discount_value' => $resolvedDiscountValue,
                    'promo_price' => $resolvedPromoPrice ? (float) $resolvedPromoPrice : null,
                    'remaining_promo_stock' => $remainingStock,
                ];

                if ($quantity >= $minQty && (is_null($remainingStock) || $remainingStock > 0)) {
                    $appliedPromo = $promoProduk;
                } else {
                    $appliedPromo = null;
                }
            } else {
                // Fallback to promo_toko
                $promoToko = $activePromotions->first(function ($promo) use ($product) {
                    if ($promo->type !== 'promo_toko' && $promo->type !== 'special_deals') {
                        return false;
                    }
                    if ($promo->items->isEmpty()) {
                        return true;
                    }

                    return $promo->items->contains(function ($i) use ($product) {
                        return $i->product_id === $product->id;
                    });
                });

                if ($promoToko) {
                    $appliedPromo = $promoToko;
                    if ($appliedPromo->items->isNotEmpty()) {
                        $appliedItem = $appliedPromo->items->first(function ($i) use ($product) {
                            return $i->product_id === $product->id && is_null($i->product_variant_id);
                        });
                    }
                }
            }
        }

        if ($appliedPromo) {
            $discountType = $appliedItem ? ($appliedItem->discount_type ?? $appliedPromo->discount_type) : $appliedPromo->discount_type;
            $discountValue = $appliedItem ? ($appliedItem->discount_value ?? $appliedPromo->discount_value) : $appliedPromo->discount_value;
            $promoPrice = $appliedItem?->promo_price;

            // Calculate direct promo unit price
            if ($promoPrice && $promoPrice > 0) {
                $rawPromoPrice = (float) $promoPrice;
            } else {
                if ($discountType === 'percentage') {
                    $rawPromoPrice = $basePrice - ($basePrice * ($discountValue / 100));
                } elseif ($discountType === 'fixed') {
                    $rawPromoPrice = $basePrice - $discountValue;
                } else {
                    $rawPromoPrice = $basePrice;
                }
            }
            $rawPromoPrice = max(0, $rawPromoPrice);

            // Handle Promo Stock & Split Pricing
            $promoQtyUsed = $quantity;
            $finalPrice = $rawPromoPrice;

            if (isset($remainingStock) && ! is_null($remainingStock)) {
                if ($remainingStock <= 0) {
                    // Fully exhausted, revert to normal
                    $appliedPromo = null;
                    $finalPrice = $basePrice;
                    $promoQtyUsed = 0;
                } elseif ($remainingStock < $quantity) {
                    // Split pricing!
                    $promoQtyUsed = $remainingStock;
                    $normalQty = $quantity - $promoQtyUsed;
                    $finalPrice = (($promoQtyUsed * $rawPromoPrice) + ($normalQty * $basePrice)) / $quantity;
                }
            }

            if ($appliedPromo && $finalPrice < $basePrice) {
                $product->is_promo = true;
                $product->promo_price = $finalPrice;
                $product->original_price = $basePrice;
                $product->promo_type = $appliedPromo->type;
                $product->promo_end_time = $appliedPromo->end_time?->toIso8601String();
                $product->keep_tier_prices = $appliedPromo->settings['keep_tier_prices'] ?? false;
                $product->applied_promotion_id = $appliedPromo->id;
                $product->promo_quantity_used = $promoQtyUsed;
                if ($basePrice > 0) {
                    $product->discount_percentage = round((($basePrice - $finalPrice) / $basePrice) * 100);
                } else {
                    $product->discount_percentage = 0;
                }
            } else {
                $product->is_promo = false;
                $product->promo_price = $basePrice;
                $product->original_price = $basePrice;
                $product->discount_percentage = 0;
                $product->promo_type = null;
                $product->promo_end_time = null;
                $product->keep_tier_prices = false;
            }
        } else {
            $product->is_promo = false;
            $product->promo_price = $basePrice;
            $product->original_price = $basePrice;
            $product->discount_percentage = 0;
            $product->promo_type = null;
            $product->promo_end_time = null;
            $product->keep_tier_prices = false;
        }

        // Apply to variants if loaded
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                $vPrice = (float) ($variant->productPrice?->price ?? 0);
                $vAppliedPromo = null;
                $vAppliedItem = null;
                $variant->promo_rule = null;
                $variant->applied_promotion_id = null;
                $variant->promo_quantity_used = null;

                if ($flashSalePromo) {
                    $vRemainingStock = $this->getRemainingPromoStock($flashSalePromo->id, $product->id, $variant->id);
                    if (! is_null($vRemainingStock)) {
                        $variant->promo_rule = [
                            'id' => $flashSalePromo->id,
                            'name' => $flashSalePromo->name,
                            'type' => $flashSalePromo->type,
                            'min_qty' => 1,
                            'remaining_promo_stock' => $vRemainingStock,
                        ];
                    }

                    if (is_null($vRemainingStock) || $vRemainingStock > 0) {
                        $vAppliedPromo = $flashSalePromo;
                        if ($vAppliedPromo->items->isNotEmpty()) {
                            $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                                return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                            });
                            if (! $vAppliedItem) {
                                $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product) {
                                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                                });
                            }
                        }
                    }
                } elseif ($isBundlingActive) {
                    $vAppliedPromo = null;
                } else {
                    if (isset($promoProduk)) {
                        $minQty = $promoProduk->settings['min_qty'] ?? 1;
                        $vRemainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, $variant->id);

                        if ($quantity >= $minQty) {
                            $vAppliedPromo = $promoProduk;
                            if ($vAppliedPromo->items->isNotEmpty()) {
                                $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                                    return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                                });
                                if (! $vAppliedItem) {
                                    $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product) {
                                        return $i->product_id === $product->id && is_null($i->product_variant_id);
                                    });
                                }
                            }
                        }

                        $vResolvedDiscountType = $vAppliedItem ? ($vAppliedItem->discount_type ?? $promoProduk->discount_type) : $promoProduk->discount_type;
                        $vResolvedDiscountValue = $vAppliedItem ? ($vAppliedItem->discount_value ?? $promoProduk->discount_value) : $promoProduk->discount_value;
                        $vResolvedPromoPrice = $vAppliedItem?->promo_price;

                        $variant->promo_rule = [
                            'id' => $promoProduk->id,
                            'name' => $promoProduk->name,
                            'type' => $promoProduk->type,
                            'min_qty' => (int) $minQty,
                            'discount_type' => $vResolvedDiscountType,
                            'discount_value' => $vResolvedDiscountValue,
                            'promo_price' => $vResolvedPromoPrice ? (float) $vResolvedPromoPrice : null,
                            'remaining_promo_stock' => $vRemainingStock,
                        ];

                        if ($quantity < $minQty || (! is_null($vRemainingStock) && $vRemainingStock <= 0)) {
                            $vAppliedPromo = null;
                        }
                    } else {
                        if (isset($promoToko)) {
                            $vAppliedPromo = $promoToko;
                            if ($vAppliedPromo->items->isNotEmpty()) {
                                $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                                    return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                                });
                                if (! $vAppliedItem) {
                                    $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product) {
                                        return $i->product_id === $product->id && is_null($i->product_variant_id);
                                    });
                                }
                            }
                        }
                    }
                }

                if ($vAppliedPromo) {
                    $vDiscountType = $vAppliedItem ? ($vAppliedItem->discount_type ?? $vAppliedPromo->discount_type) : $vAppliedPromo->discount_type;
                    $vDiscountValue = $vAppliedItem ? ($vAppliedItem->discount_value ?? $vAppliedPromo->discount_value) : $vAppliedPromo->discount_value;
                    $vPromoPrice = $vAppliedItem?->promo_price;

                    if ($vPromoPrice && $vPromoPrice > 0) {
                        $vRawPromoPrice = (float) $vPromoPrice;
                    } else {
                        if ($vDiscountType === 'percentage') {
                            $vRawPromoPrice = $vPrice - ($vPrice * ($vDiscountValue / 100));
                        } elseif ($vDiscountType === 'fixed') {
                            $vRawPromoPrice = $vPrice - $vDiscountValue;
                        } else {
                            $vRawPromoPrice = $vPrice;
                        }
                    }
                    $vRawPromoPrice = max(0, $vRawPromoPrice);

                    $vPromoQtyUsed = $quantity;
                    $vFinalPrice = $vRawPromoPrice;

                    if (isset($vRemainingStock) && ! is_null($vRemainingStock)) {
                        if ($vRemainingStock <= 0) {
                            $vAppliedPromo = null;
                            $vFinalPrice = $vPrice;
                            $vPromoQtyUsed = 0;
                        } elseif ($vRemainingStock < $quantity) {
                            $vPromoQtyUsed = $vRemainingStock;
                            $vNormalQty = $quantity - $vPromoQtyUsed;
                            $vFinalPrice = (($vPromoQtyUsed * $vRawPromoPrice) + ($vNormalQty * $vPrice)) / $quantity;
                        }
                    }

                    if ($vAppliedPromo && $vFinalPrice < $vPrice) {
                        $variant->is_promo = true;
                        $variant->promo_price = $vFinalPrice;
                        $variant->original_price = $vPrice;
                        $variant->promo_type = $vAppliedPromo->type;
                        $variant->promo_end_time = $vAppliedPromo->end_time?->toIso8601String();
                        $variant->keep_tier_prices = $vAppliedPromo->settings['keep_tier_prices'] ?? false;
                        $variant->applied_promotion_id = $vAppliedPromo->id;
                        $variant->promo_quantity_used = $vPromoQtyUsed;
                        if ($vPrice > 0) {
                            $variant->discount_percentage = round((($vPrice - $vFinalPrice) / $vPrice) * 100);
                        } else {
                            $variant->discount_percentage = 0;
                        }
                    } else {
                        $variant->is_promo = false;
                        $variant->promo_price = $vPrice;
                        $variant->original_price = $vPrice;
                        $variant->discount_percentage = 0;
                        $variant->promo_type = null;
                        $variant->promo_end_time = null;
                        $variant->keep_tier_prices = false;
                    }
                } else {
                    $variant->is_promo = false;
                    $variant->promo_price = $vPrice;
                    $variant->original_price = $vPrice;
                    $variant->discount_percentage = 0;
                    $variant->promo_type = null;
                    $variant->promo_end_time = null;
                    $variant->keep_tier_prices = false;
                }
            }
        }

        // Aggregate: find the cheapest variant to display on the base product card
        if ($product->relationLoaded('variants')) {
            if ($product->variants->isNotEmpty()) {
                $promoVariants = $product->variants->filter(function ($v) {
                    return $v->is_promo;
                });

                if ($promoVariants->isNotEmpty()) {
                    // Find the cheapest promo variant by promo_price
                    $cheapestPromoVariant = $promoVariants->sortBy('promo_price')->first();

                    $product->is_promo = true;
                    $product->promo_price = $cheapestPromoVariant->promo_price;
                    $product->original_price = $cheapestPromoVariant->original_price;
                    $product->discount_percentage = $cheapestPromoVariant->discount_percentage;
                    $product->promo_type = $cheapestPromoVariant->promo_type;
                    $product->promo_end_time = $cheapestPromoVariant->promo_end_time;
                }
            }
        }
    }

    /**
     * Get remaining promo stock for a promotion and product/variant.
     */
    private function getRemainingPromoStock($promotionId, $productId, $variantId = null): ?int
    {
        $promoItem = PromotionItem::where('promotion_id', $promotionId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if (! $promoItem && $variantId) {
            $promoItem = PromotionItem::where('promotion_id', $promotionId)
                ->where('product_id', $productId)
                ->whereNull('product_variant_id')
                ->first();
        }

        if (! $promoItem || is_null($promoItem->promo_stock)) {
            return null;
        }

        $soldPromoQty = TransactionItem::where('applied_promotion_id', $promotionId)
            ->where('product_id', $productId)
            ->where(function ($q) use ($promoItem) {
                if (! is_null($promoItem->product_variant_id)) {
                    $q->where('product_variant_id', $promoItem->product_variant_id);
                }
            })
            ->whereHas('transaction', function ($q) {
                $q->where('status', '!=', 'batal');
            })
            ->sum('promo_quantity_used');

        return max(0, $promoItem->promo_stock - $soldPromoQty);
    }

    /**
     * Get the cart items for checkout, respecting the session-based "Buy Now" flow.
     */
    private function getCheckoutItems($user)
    {
        if (session()->has('buy_now_item')) {
            $buyNowData = session('buy_now_item');

            $product = Product::with([
                'productPrice',
                'productStock',
                'images',
                'variants.productPrice',
                'variants.productStock',
                'variants.options',
            ])->find($buyNowData['product_id']);

            if ($product) {
                $variant = null;
                if (! empty($buyNowData['product_variant_id'])) {
                    $variant = $product->variants->firstWhere('id', $buyNowData['product_variant_id']);
                }

                $tempCartItem = new CartItem([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $buyNowData['quantity'],
                    'is_checked' => true,
                ]);

                // Manually assign primary key since it's in-memory mock
                $tempCartItem->id = 'buy-now-temp-id';

                $tempCartItem->setRelation('product', $product);
                if ($variant) {
                    $tempCartItem->setRelation('productVariant', $variant);
                    $tempCartItem->setRelation('product_variant', $variant);
                }

                return new Collection([$tempCartItem]);
            }
        }

        return CartItem::with([
            'product.productPrice',
            'product.productStock',
            'product.images',
            'product.variants.productPrice',
            'product.variants.productStock',
            'product.variants.options',
            'productVariant.productPrice',
            'productVariant.productStock',
            'productVariant.options',
        ])
            ->where('user_id', $user->id)
            ->where('is_checked', true)
            ->get();
    }
}
