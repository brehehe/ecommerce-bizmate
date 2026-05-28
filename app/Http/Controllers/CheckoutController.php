<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    /**
     * Display the checkout page.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Only checked cart items
        $cartItems = CartItem::with([
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

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Pilih produk terlebih dahulu sebelum checkout.');
        }

        // Load all active non-voucher promotions (includes bundling_gift)
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereNotIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
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
            $this->applyPromotionsToProduct($product, $activePromotions);

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

        return Inertia::render('Storefront/Checkout', [
            'cartItems' => $cartItems,
            'addresses' => $addresses,
            'paymentMethods' => $paymentMethods,
            'activePromotions' => $activePromotions,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'storeOriginCity' => $storeOriginCity,
            'appFee' => $appFee,
        ]);
    }

    /**
     * Process the checkout and create a transaction.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'shipping_courier' => 'required|string|max:50',
            'shipping_service' => 'required|string|max:50',
            'shipping_etd' => 'nullable|string|max:50',
            'shipping_fee' => 'required|numeric|min:0',
            'voucher_code' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Validate address belongs to user
        $address = CustomerAddress::where('user_id', $user->id)
            ->findOrFail($request->customer_address_id);

        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

        // Get checked cart items
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

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Tidak ada produk yang dipilih untuk checkout.');
        }

        // Apply active promotions
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereNotIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
            ->get();

        foreach ($cartItems as $item) {
            $this->applyPromotionsToProduct($item->product, $activePromotions);
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

        DB::transaction(function () use (
            $user, $address, $paymentMethod, $cartItems,
            $request, $subtotal, $discountAmount, $shippingFee, $shippingDiscount,
            $adminFee, $appFee, $grandTotal, $voucherCode, $voucherDiscountType, $voucherDiscountValue,
            $voucherPromotion
        ) {
            // Create transaction
            $transaction = Transaction::create([
                'transaction_number' => Transaction::generateNumber(),
                'user_id' => $user->id,
                'customer_address_id' => $address->id,
                'payment_method_id' => $paymentMethod->id,
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
            TransactionPayment::create([
                'transaction_id' => $transaction->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $grandTotal,
                'status' => 'pending',
            ]);

            // Update voucher used_count
            if ($voucherPromotion) {
                $voucherPromotion->increment('used_count');
            }

            // Remove checked cart items
            CartItem::where('user_id', $user->id)
                ->where('is_checked', true)
                ->delete();

            // Store transaction number in session for redirect
            session(['last_transaction_number' => $transaction->transaction_number]);

            return $transaction;
        });

        $transactionNumber = session('last_transaction_number');
        $transaction = Transaction::where('transaction_number', $transactionNumber)->first();

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

        $cartItems = CartItem::where('user_id', $user->id)
            ->where('is_checked', true)
            ->get();

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
            'destination' => 'required|integer',
            'weight' => 'required|integer|min:1',
            'courier' => 'required|string',
        ]);

        $apiKey = Setting::where('key', 'rajaongkir_key')->value('value')
            ?? config('app.rajaongkir.key');
        $origin = Setting::where('key', 'rajaongkir_origin')->value('value')
            ?? Setting::where('key', 'regency_id')->value('value');
        $baseUrl = Setting::where('key', 'rajaongkir_url')->value('value')
            ?? config('app.rajaongkir.url', 'https://rajaongkir.komerce.id/api/v1/');

        if (! $apiKey || ! $origin) {
            return response()->json(['error' => 'Konfigurasi RajaOngkir atau kota asal belum diatur di pengaturan.'], 422);
        }

        try {
            $isKomerce = str_contains($baseUrl, 'komerce.id');
            $endpoint = $isKomerce ? 'calculate/domestic-cost' : 'cost';

            $response = Http::withHeaders(['key' => $apiKey])
                ->asForm()
                ->post(rtrim($baseUrl, '/').'/'.$endpoint, [
                    'origin' => $origin,
                    'destination' => $request->destination,
                    'weight' => $request->weight,
                    'courier' => $request->courier,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($isKomerce) {
                    $results = [];
                    $items = $data['data'] ?? [];
                    $costs = [];

                    foreach ($items as $item) {
                        $costs[] = [
                            'service' => $item['service'],
                            'description' => $item['description'] ?? $item['service'],
                            'cost' => [
                                [
                                    'value' => (float) ($item['cost'] ?? 0),
                                    'etd' => $item['etd'] ?? '',
                                    'note' => '',
                                ],
                            ],
                        ];
                    }

                    if (! empty($costs)) {
                        $results = [
                            [
                                'code' => $request->courier,
                                'name' => $items[0]['name'] ?? strtoupper($request->courier),
                                'costs' => $costs,
                            ],
                        ];
                    }
                } else {
                    $results = $data['rajaongkir']['results'] ?? [];
                }

                return response()->json(['results' => $results]);
            }

            return response()->json(['error' => 'Gagal mendapatkan data ongkir.'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal terhubung ke layanan pengiriman.'], 422);
        }
    }

    /**
     * Proxy RajaOngkir API to get city list.
     */
    public function cities(Request $request)
    {
        $apiKey = Setting::where('key', 'rajaongkir_key')->value('value')
            ?? config('app.rajaongkir.key');
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
        $promotion = Promotion::where('code', strtoupper($code))
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
            ->first();

        if (! $promotion) {
            return [
                'valid' => false,
                'message' => 'Kode voucher tidak valid atau sudah kadaluarsa.',
                'promotion' => null,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'shipping_discount' => 0,
            ];
        }

        // Check quota
        if ($promotion->quota !== null && $promotion->used_count >= $promotion->quota) {
            return [
                'valid' => false,
                'message' => 'Kuota voucher telah habis.',
                'promotion' => null,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'shipping_discount' => 0,
            ];
        }

        // Calculate subtotal if not passed
        if ($subtotal === null) {
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $subtotal += $item->unit_price * $item->quantity;
            }
        }

        // Check minimum purchase
        if ($promotion->min_purchase && $subtotal < $promotion->min_purchase) {
            return [
                'valid' => false,
                'message' => 'Minimum pembelian Rp '.number_format($promotion->min_purchase, 0, ',', '.').' untuk menggunakan voucher ini.',
                'promotion' => null,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'shipping_discount' => 0,
            ];
        }

        $discountAmount = 0;
        $shippingDiscount = 0;

        if ($promotion->type === 'voucher_gratis_ongkir') {
            // Cap at actual shipping fee
            $shippingDiscount = min($shippingFee, (float) ($promotion->max_discount ?? $shippingFee));
        } elseif ($promotion->type === 'voucher_belanja') {
            if ($promotion->discount_type === 'percentage') {
                $discountAmount = $subtotal * ($promotion->discount_value / 100);
            } else {
                $discountAmount = (float) $promotion->discount_value;
            }

            // Apply max discount cap
            if ($promotion->max_discount) {
                $discountAmount = min($discountAmount, (float) $promotion->max_discount);
            }
        }

        return [
            'valid' => true,
            'message' => 'Voucher berhasil diterapkan!',
            'promotion' => $promotion,
            'promotion_name' => $promotion->name,
            'discount_type' => $promotion->discount_type,
            'discount_value' => (float) $promotion->discount_value,
            'discount_amount' => round($discountAmount, 2),
            'shipping_discount' => round($shippingDiscount, 2),
        ];
    }

    /**
     * Apply active promotions to a product (reused from CartController).
     */
    private function applyPromotionsToProduct($product, $activePromotions): void
    {
        $basePrice = (float) ($product->productPrice?->price ?? 0);
        $appliedPromo = null;
        $appliedItem = null;

        foreach ($activePromotions as $promo) {
            if ($promo->items->isEmpty()) {
                if (! $appliedPromo) {
                    $appliedPromo = $promo;
                }
            } else {
                $item = $promo->items->first(function ($i) use ($product) {
                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                });
                if ($item) {
                    $appliedPromo = $promo;
                    $appliedItem = $item;
                    break;
                }
            }
        }

        if ($appliedPromo) {
            $discountType = $appliedItem ? ($appliedItem->discount_type ?? $appliedPromo->discount_type) : $appliedPromo->discount_type;
            $discountValue = $appliedItem ? ($appliedItem->discount_value ?? $appliedPromo->discount_value) : $appliedPromo->discount_value;
            $promoPrice = $appliedItem?->promo_price;

            if ($promoPrice && $promoPrice > 0) {
                $finalPrice = (float) $promoPrice;
            } elseif ($discountType === 'percentage') {
                $finalPrice = $basePrice - ($basePrice * ($discountValue / 100));
            } elseif ($discountType === 'fixed') {
                $finalPrice = $basePrice - $discountValue;
            } else {
                $finalPrice = $basePrice;
            }

            $finalPrice = max(0, $finalPrice);

            if ($finalPrice < $basePrice) {
                $product->is_promo = true;
                $product->promo_price = $finalPrice;
                $product->original_price = $basePrice;
            } else {
                $product->is_promo = false;
                $product->promo_price = $basePrice;
                $product->original_price = $basePrice;
            }
        } else {
            $product->is_promo = false;
            $product->promo_price = $basePrice;
            $product->original_price = $basePrice;
        }

        // Apply to variants
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                $vPrice = (float) ($variant->productPrice?->price ?? 0);
                $vAppliedPromo = null;
                $vAppliedItem = null;

                foreach ($activePromotions as $promo) {
                    if ($promo->items->isEmpty()) {
                        if (! $vAppliedPromo) {
                            $vAppliedPromo = $promo;
                        }
                    } else {
                        $item = $promo->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        });
                        if (! $item) {
                            $item = $promo->items->first(function ($i) use ($product) {
                                return $i->product_id === $product->id && is_null($i->product_variant_id);
                            });
                        }
                        if ($item) {
                            $vAppliedPromo = $promo;
                            $vAppliedItem = $item;
                            break;
                        }
                    }
                }

                if ($vAppliedPromo) {
                    $vDiscountType = $vAppliedItem ? ($vAppliedItem->discount_type ?? $vAppliedPromo->discount_type) : $vAppliedPromo->discount_type;
                    $vDiscountValue = $vAppliedItem ? ($vAppliedItem->discount_value ?? $vAppliedPromo->discount_value) : $vAppliedPromo->discount_value;
                    $vPromoPrice = $vAppliedItem?->promo_price;

                    if ($vPromoPrice && $vPromoPrice > 0) {
                        $vFinalPrice = (float) $vPromoPrice;
                    } elseif ($vDiscountType === 'percentage') {
                        $vFinalPrice = $vPrice - ($vPrice * ($vDiscountValue / 100));
                    } elseif ($vDiscountType === 'fixed') {
                        $vFinalPrice = $vPrice - $vDiscountValue;
                    } else {
                        $vFinalPrice = $vPrice;
                    }

                    $vFinalPrice = max(0, $vFinalPrice);

                    $variant->is_promo = $vFinalPrice < $vPrice;
                    $variant->promo_price = $vFinalPrice;
                    $variant->original_price = $vPrice;
                } else {
                    $variant->is_promo = false;
                    $variant->promo_price = $vPrice;
                    $variant->original_price = $vPrice;
                }
            }
        }
    }
}
