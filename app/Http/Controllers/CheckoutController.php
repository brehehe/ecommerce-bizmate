<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\ApplyVoucherAction;
use App\Actions\Checkout\ProcessCheckoutAction;
use App\Actions\Checkout\UploadPaymentProofAction;
use App\Http\Requests\Checkout\ApplyVoucherRequest;
use App\Http\Requests\Checkout\ProcessCheckoutRequest;
use App\Http\Requests\Checkout\ShippingCostRequest;
use App\Http\Requests\Checkout\UploadProofRequest;
use App\Models\CartItem;
use App\Models\Courier;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BiteshipService;
use App\Services\Checkout\ShippingCalculationService;
use App\Services\KomerceService;
use App\Services\MembershipService;
use App\Services\MidtransService;
use App\Services\Promotion\PromotionCalculationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly MembershipService $membershipService,
        private readonly PromotionCalculationService $promotionService,
        private readonly ShippingCalculationService $shippingCalculationService,
        private readonly ProcessCheckoutAction $processCheckoutAction,
        private readonly ApplyVoucherAction $applyVoucherAction,
        private readonly UploadPaymentProofAction $uploadPaymentProofAction
    ) {}

    /**
     * Display the checkout page.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        KomerceService::syncPaymentMethods();

        $user = $request->user();
        $cartItems = $this->getCheckoutItems($user);

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Pilih produk terlebih dahulu sebelum checkout.');
        }

        $activePromotions = $this->promotionService->getActiveNonVoucherPromotions();
        $this->promotionService->populateBundlingProducts($activePromotions);

        foreach ($cartItems as $item) {
            $product = $item->product;
            $variant = $item->productVariant;

            $this->promotionService->applyPromotionsToProduct($product, $activePromotions, $item->quantity);

            if ($variant) {
                $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                if ($matchedVariant) {
                    $variant = $matchedVariant;
                    $item->setRelation('productVariant', $matchedVariant);
                }
            }

            $basePrice = $variant
                ? (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0))
                : (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));

            $stock = (int) ($variant ? ($variant->productStock?->stock ?? 0) : ($product->productStock?->stock ?? 0));
            $isUnlimited = (bool) ($variant ? ($variant->productStock?->is_unlimited ?? false) : ($product->productStock?->is_unlimited ?? false));
            $tiers = $variant ? $variant->tierPrices : $product->tierPrices;

            $appliedTier = null;
            $effectivePrice = $basePrice;

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

            $item->setAttribute('computed_base_price', $basePrice);
            $item->setAttribute('computed_price', $effectivePrice);
            $item->setAttribute('unit_price', $effectivePrice);
            $item->setAttribute('subtotal', $effectivePrice * $item->quantity);
            $item->setAttribute('price', $effectivePrice);
            $item->setAttribute('computed_stock', $stock);
            $item->setAttribute('computed_is_unlimited', $isUnlimited);
            $item->setAttribute('applied_tier', $appliedTier);
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

        $checkoutPromoSnapshot = [];
        foreach ($cartItems as $item) {
            $variant = $item->productVariant;
            $product = $item->product;
            $key = $item->product_variant_id ? "v_{$item->product_variant_id}" : "p_{$item->product_id}";
            $price = (float) ($item->unit_price ?? ($variant
                ? ($variant->is_promo ? (float) $variant->promo_price : (float) ($variant->productPrice?->price ?? 0))
                : ($product->is_promo ? (float) $product->promo_price : (float) ($product->productPrice?->price ?? 0))));
            $checkoutPromoSnapshot[$key] = [
                'price' => $price,
                'is_promo' => (bool) ($variant ? ($variant->is_promo ?? false) : ($product->is_promo ?? false)),
                'promo_price' => $variant ? $variant->promo_price : $product->promo_price,
            ];
        }
        session(['checkout_promo_prices' => $checkoutPromoSnapshot]);

        $addresses = CustomerAddress::where('user_id', $user->id)
            ->orderBy('is_primary', 'desc')
            ->get();

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $couriers = Courier::where('is_active', true)
            ->orderBy('name')
            ->get();

        $cartVoucherCode = session('cart_voucher_code');
        $appliedVoucher = null;

        $subtotal = 0;
        foreach ($cartItems as $item) {
            $variant = $item->productVariant;
            $product = $item->product;
            $price = $variant
                ? ($variant->is_promo ? (float) $variant->promo_price : (float) ($variant->productPrice?->price ?? 0))
                : ($product->is_promo ? (float) $product->promo_price : (float) ($product->productPrice?->price ?? 0));
            $subtotal += $price * $item->quantity;
        }

        if ($cartVoucherCode) {
            $voucherResult = $this->applyVoucherAction->execute($cartVoucherCode, 0, $subtotal, $cartItems);
            if ($voucherResult['valid']) {
                $appliedVoucher = [
                    'code' => $cartVoucherCode,
                    'discount_type' => $voucherResult['discount_type'],
                    'discount_value' => $voucherResult['discount_value'],
                    'discount_amount' => $voucherResult['discount_amount'],
                    'shipping_discount' => $voucherResult['shipping_discount'],
                    'promotion' => $voucherResult['promotion'],
                ];
            }
        }

        $allVouchers = Promotion::where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
            ->get();

        $additionalCostsJson = Setting::where('key', 'additional_costs')->value('value');
        $additionalCosts = $additionalCostsJson ? json_decode($additionalCostsJson, true) : [];

        $storeAddress = CustomerAddress::whereHas('user', function ($q) {
            $q->where('is_seller', true);
        })->where('is_primary', true)->first();

        $storeOriginCityId = Setting::where('key', 'rajaongkir_origin')->value('value')
            ?? Setting::where('key', 'regency_id')->value('value');

        $storeLat = Setting::where('key', 'latitude')->value('value');
        $storeLng = Setting::where('key', 'longitude')->value('value');

        return Inertia::render('Storefront/Checkout', [
            'cartItems' => $cartItems,
            'addresses' => $addresses,
            'paymentMethods' => $paymentMethods,
            'couriers' => $couriers,
            'appliedVoucher' => $appliedVoucher,
            'allVouchers' => $allVouchers,
            'additionalCosts' => $additionalCosts,
            'midtransPaymentTypes' => MidtransService::$paymentTypes,
            'membershipBenefits' => $this->membershipService->getMembershipCheckoutBenefits($user),
            'storeAddress' => $storeAddress,
            'storeOriginCityId' => $storeOriginCityId,
            'storeLat' => $storeLat,
            'storeLng' => $storeLng,
            'coinsBalance' => $user->coins_balance ?? 0,
            'coinsEnabled' => Setting::where('key', 'coins_enabled')->value('value') === '1',
            'isSelfPickupEnabled' => Setting::where('key', 'self_pickup_enabled')->value('value') === '1',
            'selfPickupFee' => (float) (Setting::where('key', 'self_pickup_fee')->value('value') ?? 0),
            'isStoreCourierEnabled' => Setting::where('key', 'store_courier_enabled')->value('value') === '1',
            'biteshipEnabled' => BiteshipService::isEnabled(),
        ]);
    }

    /**
     * Process checkout form submission.
     */
    public function store(ProcessCheckoutRequest $request): RedirectResponse
    {
        if (config('app.checkout_locked')) {
            $message = config('app.checkout_locked_message', 'Checkout sedang dinonaktifkan sementara. Silakan coba lagi nanti.');

            return back()->with('error', $message);
        }

        $user = $request->user();
        $cartItems = $this->getCheckoutItems($user);

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Tidak ada produk yang dipilih untuk checkout.');
        }

        // Apply active promotions
        $activePromotions = $this->promotionService->getActiveNonVoucherPromotions();
        foreach ($cartItems as $item) {
            $this->promotionService->applyPromotionsToProduct($item->product, $activePromotions, $item->quantity);
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

        // Validate physical stock availability
        foreach ($cartItems as $item) {
            $variant = $item->productVariant;
            $product = $item->product;

            $stockRecord = $variant ? $variant->productStock : $product->productStock;

            if ($stockRecord && ! $stockRecord->is_unlimited) {
                if ($stockRecord->stock <= 0) {
                    return redirect()->route('cart.index')->with('error', "Stok produk {$product->name} sudah habis. Silakan perbarui keranjang Anda.");
                }

                if ($stockRecord->stock < $item->quantity) {
                    return redirect()->route('cart.index')->with('error', "Stok produk {$product->name} tidak mencukupi. Tersisa {$stockRecord->stock} pcs, Anda memesan {$item->quantity} pcs.");
                }
            }
        }

        $paymentMethod = PaymentMethod::find($request->input('payment_method_id'));
        if ($paymentMethod && str_contains(strtolower($paymentMethod->name), 'cod')) {
            $courier = strtolower((string) $request->input('shipping_courier'));
            $incompatibleCouriers = ['gosend', 'grab', 'lion'];
            if (in_array($courier, $incompatibleCouriers)) {
                return back()->with('error', "Metode pembayaran Cash on Delivery (COD) tidak didukung oleh kurir {$request->input('shipping_courier')}.");
            }
        }

        $transaction = $this->processCheckoutAction->execute($user, $request->validated(), $cartItems);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Pesanan berhasil dibuat!');
    }

    /**
     * Apply voucher code.
     */
    public function applyVoucher(ApplyVoucherRequest $request): JsonResponse
    {
        $user = $request->user();
        $cartItems = $this->getCheckoutItems($user);
        $activePromotions = $this->promotionService->getActiveNonVoucherPromotions();
        $this->promotionService->populateBundlingProducts($activePromotions);
        foreach ($cartItems as $item) {
            $this->promotionService->applyPromotionsToProduct($item->product, $activePromotions, $item->quantity);
        }

        $subtotal = (float) $request->input('subtotal');
        $shippingFee = (float) $request->input('shipping_fee', 0);

        $result = $this->applyVoucherAction->execute($request->input('code'), $shippingFee, $subtotal, $cartItems, $user);

        return response()->json($result);
    }

    /**
     * Upload proof of payment.
     */
    public function uploadProof(UploadProofRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('view', $transaction);

        $file = $request->file('proof_image') ?? $request->file('proof_of_payment');
        $this->uploadPaymentProofAction->execute($transaction, $file, $request->user());

        return back()->with('success', 'Bukti pembayaran berhasil diunggah.');
    }

    /**
     * Calculate shipping cost.
     */
    public function shippingCost(ShippingCostRequest $request): JsonResponse
    {
        $user = $request->user();
        $cartItems = $user ? $this->getCheckoutItems($user) : collect();

        return $this->shippingCalculationService->calculate($request->validated(), $cartItems);
    }

    /**
     * Get checked cart items for user.
     *
     * @return Collection<int, CartItem>
     */
    protected function getCheckoutItems(User $user): Collection
    {
        if (session()->has('buy_now_item')) {
            $buyNow = session('buy_now_item');
            $product = Product::with(['productPrice', 'productStock', 'images', 'tierPrices', 'variants.productPrice', 'variants.productStock', 'variants.options', 'variants.tierPrices'])->find($buyNow['product_id']);

            if (! $product) {
                session()->forget('buy_now_item');

                return new Collection;
            }

            $fakeItem = new CartItem([
                'id' => 'buy-now',
                'user_id' => $user->id,
                'product_id' => $product->id,
                'product_variant_id' => $buyNow['product_variant_id'] ?? null,
                'quantity' => $buyNow['quantity'] ?? 1,
                'is_checked' => true,
            ]);

            $fakeItem->setRelation('product', $product);
            if (! empty($buyNow['product_variant_id'])) {
                $variant = $product->variants->firstWhere('id', $buyNow['product_variant_id']);
                $fakeItem->setRelation('productVariant', $variant);
            }

            return new Collection([$fakeItem]);
        }

        return CartItem::with([
            'product.productPrice',
            'product.productStock',
            'product.images',
            'product.tierPrices',
            'productVariant.productPrice',
            'productVariant.productStock',
            'productVariant.options',
            'product.variants.productPrice',
            'product.variants.productStock',
            'product.variants.options',
        ])
            ->where('user_id', $user->id)
            ->where('is_checked', true)
            ->get();
    }

    /**
     * Search Komerce/Biteship destination.
     */
    public function searchKomerceDestination(Request $request): JsonResponse
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
    public function internationalDestinations(Request $request): JsonResponse
    {
        $destinations = KomerceService::getInternationalDestinations();

        return response()->json(['destinations' => $destinations]);
    }

    /**
     * Proxy RajaOngkir API to get city list.
     */
    public function cities(Request $request): JsonResponse
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

            return response()->json(['error' => 'Gagal mengambil data kota.'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
