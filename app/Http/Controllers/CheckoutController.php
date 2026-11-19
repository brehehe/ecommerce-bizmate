<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\ApplyVoucherAction;
use App\Actions\Checkout\ProcessCheckoutAction;
use App\Actions\Checkout\UploadPaymentProofAction;
use App\Http\Requests\Checkout\ApplyVoucherRequest;
use App\Http\Requests\Checkout\ProcessCheckoutRequest;
use App\Http\Requests\Checkout\ShippingCostRequest;
use App\Http\Requests\Checkout\UploadProofRequest;
use App\Mail\OrderConfirmation;
use App\Models\CartItem;
use App\Models\Courier;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RestoProfile;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

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
        if ($user && $user->hasRole('Customer') && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $isGuest = ($user === null);

        if (! $user && ! config('app.is_checkout_without_login', false)) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk checkout.');
        }

        $cartItems = $this->getCheckoutItems($user, $isGuest);

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Pilih produk terlebih dahulu sebelum checkout.');
        }

        if (Product::isFoodPickEnabled()) {
            $uniqueRestos = $cartItems->pluck('product.user_id')->filter()->unique();
            if ($uniqueRestos->count() > 1) {
                return redirect()->route('cart.index')->with('error', 'Pesanan makanan hanya boleh berasal dari 1 Resto dalam satu transaksi.');
            }
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
            $modifierExtra = 0.0;
            if (! empty($item->selected_modifiers) && is_iterable($item->selected_modifiers)) {
                $modifierExtra = (float) collect($item->selected_modifiers)->sum('additional_price');
            }

            $effectivePrice = $basePrice + $modifierExtra;

            if ($tiers && $tiers->isNotEmpty()) {
                $matchingTiers = $tiers->filter(fn ($t) => $item->quantity >= $t->min_qty)->sortByDesc('min_qty');
                if ($matchingTiers->isNotEmpty()) {
                    $appliedTier = $matchingTiers->first();
                    $tierPriceVal = (float) $appliedTier->price + $modifierExtra;
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

        $addresses = $user
            ? CustomerAddress::where('user_id', $user->id)
                ->orderBy('is_primary', 'desc')
                ->get()
            : collect([]);

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $isFoodPick = (bool) config('app.is_foodpick', false);
        $foodpickConfig = config('app.foodpick', []);
        $hideExpedition = $isFoodPick && ($foodpickConfig['hide_expedition'] ?? true);
        $restoProfile = null;

        if ($isFoodPick) {
            $restoId = $cartItems->first()?->product?->user_id;
            if ($restoId) {
                $restoProfile = RestoProfile::where('user_id', $restoId)->first();
            }
            if (! $restoProfile) {
                $restoProfile = RestoProfile::first();
            }

            if ($restoProfile) {
                $resolvedMethods = $restoProfile->getResolvedPaymentMethods();
                $customMethods = collect();

                $codMaster = PaymentMethod::where('type', 'manual')->where(function ($q) {
                    $q->where('bank_name', 'COD')->orWhere('name', 'like', '%COD%');
                })->first() ?: PaymentMethod::firstOrCreate(
                    ['bank_name' => 'COD'],
                    [
                        'name' => 'Bayar di Tempat (COD / Kasir Resto)',
                        'type' => 'manual',
                        'is_active' => true,
                        'admin_fee' => 0,
                        'settings' => [
                            'code' => 'cod',
                            'description' => 'Bayar tunai langsung saat makanan diambil di resto atau diantar kurir.',
                        ],
                    ]
                );

                $bankMaster = PaymentMethod::where('type', 'manual')->where(function ($q) {
                    $q->where('name', 'like', '%Transfer Bank Resto%')->orWhere('name', 'like', '%Transfer Bank%');
                })->first() ?: PaymentMethod::firstOrCreate(
                    ['name' => 'Transfer Bank Resto'],
                    [
                        'type' => 'manual',
                        'bank_name' => 'BCA',
                        'is_active' => true,
                        'admin_fee' => 0,
                        'settings' => [
                            'description' => 'Transfer manual langsung ke rekening resto dan kirim bukti bayar.',
                        ],
                    ]
                );

                $qrisMaster = PaymentMethod::where('type', 'manual')->where(function ($q) {
                    $q->where('bank_name', 'QRIS')->orWhere('name', 'like', '%QRIS Resto%');
                })->first() ?: PaymentMethod::firstOrCreate(
                    ['name' => 'QRIS Resto'],
                    [
                        'type' => 'manual',
                        'bank_name' => 'QRIS',
                        'is_active' => true,
                        'admin_fee' => 0,
                        'settings' => [
                            'description' => 'Scan QRIS resto menggunakan GoPay, OVO, ShopeePay, DANA, atau Mobile Banking.',
                        ],
                    ]
                );

                // 1. COD
                if (! empty($resolvedMethods['cod']['is_active']) && $codMaster) {
                    $item = clone $codMaster;
                    $item->name = $resolvedMethods['cod']['label'] ?? 'Bayar di Tempat (COD / Kasir Resto)';
                    $item->bank_name = 'COD';
                    $item->account_number = null;
                    $item->account_name = null;
                    $item->description = $resolvedMethods['cod']['description'] ?? 'Bayar tunai saat pesanan diterima.';
                    $item->settings = array_merge($item->settings ?? [], [
                        'code' => 'cod',
                        'description' => $item->description,
                    ]);
                    $customMethods->push($item);
                }

                // 2. Transfer Bank Resto
                if (! empty($resolvedMethods['bank_transfer']['is_active']) && $bankMaster) {
                    $item = clone $bankMaster;
                    $bankName = $resolvedMethods['bank_transfer']['bank_name'] ?? 'BCA';
                    $item->name = ($resolvedMethods['bank_transfer']['label'] ?? 'Transfer Bank Resto')." ({$bankName})";
                    $item->bank_name = $bankName;
                    $item->account_number = $resolvedMethods['bank_transfer']['account_number'] ?? '-';
                    $item->account_name = $resolvedMethods['bank_transfer']['account_name'] ?? ($restoProfile->resto_name ?? 'Dapur Resto');
                    $item->description = $resolvedMethods['bank_transfer']['description'] ?? 'Transfer langsung ke rekening resto.';
                    $item->settings = array_merge($item->settings ?? [], [
                        'code' => 'bank_transfer',
                        'bank_name' => $item->bank_name,
                        'account_number' => $item->account_number,
                        'account_name' => $item->account_name,
                        'description' => $item->description,
                    ]);
                    $customMethods->push($item);
                }

                // 3. QRIS Resto
                if (! empty($resolvedMethods['qris']['is_active']) && $qrisMaster) {
                    $item = clone $qrisMaster;
                    $item->name = $resolvedMethods['qris']['label'] ?? 'QRIS Resto (Semua E-Wallet)';
                    $item->bank_name = 'QRIS';
                    $item->account_number = null;
                    $item->account_name = $resolvedMethods['qris']['merchant_name'] ?? ($restoProfile->resto_name ?? 'Dapur Resto');
                    $item->description = $resolvedMethods['qris']['description'] ?? 'Scan QRIS resto menggunakan GoPay, OVO, ShopeePay, DANA.';
                    $qrisPath = $resolvedMethods['qris']['image_path'] ?? $restoProfile->qris_image_path ?? null;
                    $item->settings = array_merge($item->settings ?? [], [
                        'code' => 'qris',
                        'qris_image_path' => $qrisPath ? (str_starts_with($qrisPath, 'http') ? $qrisPath : ('/storage/'.ltrim($qrisPath, '/'))) : null,
                        'merchant_name' => $item->account_name,
                        'description' => $item->description,
                    ]);
                    $customMethods->push($item);
                }

                // 4. WARIS QRIS Dinamis Resto
                if (! empty($resolvedMethods['waris']['is_active'])) {
                    $warisMaster = PaymentMethod::where('is_active', true)
                        ->where(function ($q) {
                            $q->where('type', 'gateway')
                                ->where(function ($q2) {
                                    $q2->where('settings->gateway', 'waris')
                                        ->orWhere('name', 'like', '%Waris%')
                                        ->orWhere('name', 'like', '%QRIS%');
                                });
                        })
                        ->first() ?: PaymentMethod::firstOrCreate(
                            ['name' => 'QRIS (Semua Pembayaran / E-Wallet)'],
                            [
                                'type' => 'gateway',
                                'api_key' => config('services.waris.api_key') ?: '',
                                'api_secret' => config('services.waris.webhook_secret') ?: '',
                                'admin_fee' => 0,
                                'is_active' => true,
                                'settings' => [
                                    'gateway' => 'waris',
                                    'url' => config('services.waris.url') ?: 'https://webapiqris.biz.id/api/v1',
                                    'qris_code' => config('services.waris.default_qris_code') ?: 'random',
                                ],
                            ]
                        );

                    if ($warisMaster) {
                        $item = clone $warisMaster;
                        $item->name = $resolvedMethods['waris']['label'] ?? 'WARIS QRIS Dinamis Resto';
                        $item->type = 'gateway';
                        $item->bank_name = 'WARIS QRIS';
                        $item->account_number = null;
                        $item->account_name = $resolvedMethods['waris']['qris_code'] ?? 'random';
                        $item->description = $resolvedMethods['waris']['description'] ?? 'Generate QRIS otomatis real-time dengan verifikasi instan via Webhook.';
                        $item->admin_fee = $resolvedMethods['waris']['admin_fee'] ?? 0;
                        $item->settings = array_merge($item->settings ?? [], [
                            'code' => 'waris',
                            'gateway' => 'waris',
                            'api_url' => $resolvedMethods['waris']['api_url'] ?? '',
                            'qris_code' => $resolvedMethods['waris']['qris_code'] ?? 'random',
                            'admin_fee' => $item->admin_fee,
                            'description' => $item->description,
                        ]);
                        $customMethods->push($item);
                    }
                }

                $paymentMethods = $customMethods;
            }
        }

        $couriers = $hideExpedition
            ? collect([])
            : Courier::where('is_active', true)
                ->orderBy('name')
                ->get();

        $cartVoucherCode = session('cart_voucher_code');
        $appliedVoucher = null;

        $subtotal = 0;
        foreach ($cartItems as $item) {
            $price = (float) ($item->unit_price ?? $item->computed_price ?? $item->price ?? 0);
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

        if ($isFoodPick && $restoProfile) {
            if ($restoProfile->latitude && $restoProfile->longitude) {
                $storeLat = (float) $restoProfile->latitude;
                $storeLng = (float) $restoProfile->longitude;
            }
        }

        return Inertia::render('Storefront/Checkout', [
            'cartItems' => $cartItems,
            'addresses' => $addresses,
            'paymentMethods' => $paymentMethods,
            'couriers' => $couriers,
            'appliedVoucher' => $appliedVoucher,
            'allVouchers' => $allVouchers,
            'additionalCosts' => $additionalCosts,
            'midtransPaymentTypes' => MidtransService::$paymentTypes,
            'membershipBenefits' => $user ? $this->membershipService->getMembershipCheckoutBenefits($user) : [],
            'storeAddress' => $storeAddress,
            'storeOriginCityId' => $storeOriginCityId,
            'storeLat' => $storeLat,
            'storeLng' => $storeLng,
            'restoProfile' => $restoProfile ? [
                'id' => $restoProfile->id,
                'user_id' => $restoProfile->user_id,
                'resto_name' => $restoProfile->resto_name,
                'address' => $restoProfile->address,
                'latitude' => (float) $restoProfile->latitude,
                'longitude' => (float) $restoProfile->longitude,
                'allow_pickup' => (bool) $restoProfile->allow_pickup,
                'allow_delivery' => (bool) $restoProfile->allow_delivery,
                'max_delivery_radius_km' => (float) ($restoProfile->max_delivery_radius_km ?: 10),
                'delivery_fee_type' => $restoProfile->delivery_fee_type ?: 'flat',
                'delivery_fee_base' => (float) ($restoProfile->delivery_fee_base ?? 10000),
                'delivery_free_min_order' => (float) ($restoProfile->delivery_free_min_order ?? 0),
                'avg_prep_time_minutes' => (int) ($restoProfile->avg_prep_time_minutes ?: 20),
                'is_open_now' => $restoProfile->is_open_now !== false,
            ] : null,
            'coinsBalance' => $user->coins_balance ?? 0,
            'coinsEnabled' => Setting::where('key', 'coins_enabled')->value('value') === '1',
            'isSelfPickupEnabled' => $isFoodPick
                ? (bool) ($foodpickConfig['allow_self_pickup'] ?? true)
                : (Setting::where('key', 'self_pickup_enabled')->value('value') === '1'),
            'selfPickupFee' => (float) (Setting::where('key', 'self_pickup_fee')->value('value') ?? 0),
            'appFee' => (float) (Setting::where('key', 'shipping_rate')->value('value') ?? 0),
            'isStoreCourierEnabled' => $isFoodPick
                ? (bool) ($foodpickConfig['allow_store_courier'] ?? true)
                : (Setting::where('key', 'store_courier_enabled')->value('value') === '1'),
            'biteshipEnabled' => $hideExpedition ? false : BiteshipService::isEnabled(),
            'isGuestCheckout' => $user === null,
            'isFoodPick' => $isFoodPick,
            'foodpick' => [
                'allow_store_courier' => $isFoodPick ? (bool) ($foodpickConfig['allow_store_courier'] ?? true) : false,
                'allow_self_pickup' => $isFoodPick ? (bool) ($foodpickConfig['allow_self_pickup'] ?? true) : false,
                'hide_expedition' => $hideExpedition,
                'default_prep_minutes' => (int) ($foodpickConfig['default_prep_minutes'] ?? 20),
                'slot_interval_minutes' => (int) ($foodpickConfig['slot_interval_minutes'] ?? 15),
            ],
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
        if ($user && $user->hasRole('Customer') && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user && ! config('app.is_checkout_without_login', false)) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk checkout.');
        }

        $isGuest = ($user === null);
        if ($isGuest) {
            $customerName = trim((string) $request->input('customer_name'));
            $customerEmail = trim(strtolower((string) $request->input('customer_email')));
            $customerPhone = trim((string) $request->input('customer_phone'));

            $user = User::where('email', $customerEmail)->first();
            if (! $user) {
                $user = User::create([
                    'name' => $customerName ?: 'Customer',
                    'email' => $customerEmail,
                    'phone_number' => $customerPhone,
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => now(),
                ]);

                if (Role::where('name', 'Customer')->exists()) {
                    $user->assignRole('Customer');
                }
            } else {
                if (empty($user->phone_number) && ! empty($customerPhone)) {
                    $user->update(['phone_number' => $customerPhone]);
                }
            }

            if ($request->input('shipping_courier') !== 'self_pickup' && $request->input('shipping_courier') !== 'digital') {
                $address = CustomerAddress::create([
                    'user_id' => $user->id,
                    'label' => 'Alamat Pengiriman',
                    'receiver_name' => $request->input('receiver_name') ?: $customerName,
                    'phone_number' => $request->input('phone_number') ?: $customerPhone,
                    'full_address' => (string) $request->input('full_address'),
                    'province_id' => $request->input('province_id'),
                    'province_name' => (string) $request->input('province_name'),
                    'regency_id' => $request->input('regency_id'),
                    'regency_name' => (string) $request->input('regency_name'),
                    'district_id' => $request->input('district_id'),
                    'district_name' => (string) $request->input('district_name'),
                    'village_id' => $request->input('village_id'),
                    'village_name' => $request->input('village_name'),
                    'postal_code' => (string) $request->input('postal_code'),
                    'biteship_area_id' => $request->input('biteship_area_id'),
                    'rajaongkir_destination_id' => $request->input('rajaongkir_destination_id'),
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'is_primary' => false,
                ]);
                $request->merge(['customer_address_id' => $address->id]);
            }
        }

        $cartItems = $this->getCheckoutItems($user, $isGuest);

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Tidak ada produk yang dipilih untuk checkout.');
        }

        if (Product::isFoodPickEnabled()) {
            $uniqueRestos = $cartItems->pluck('product.user_id')->filter()->unique();
            if ($uniqueRestos->count() > 1) {
                return back()->with('error', 'Pesanan makanan hanya boleh berasal dari 1 Resto dalam satu transaksi.');
            }
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

        // Queue order confirmation email
        try {
            $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
            $storeLogo = Setting::where('key', 'store_logo')->value('value');
            $recipientEmail = $transaction->customer_email;

            if (! empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                Mail::to($recipientEmail)->queue(
                    new OrderConfirmation(
                        $transaction->load(['items.product', 'customerAddress', 'paymentMethod']),
                        $storeName,
                        $storeLogo
                    )
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to queue order confirmation email: '.$e->getMessage());
        }

        if ($isGuest) {
            session()->forget('guest_cart');
            session()->forget('buy_now_item');
            session(['guest_order_'.$transaction->transaction_number => true]);

            return redirect()->route('order.track', ['trx' => $transaction->transaction_number])
                ->with('success', 'Pesanan #'.$transaction->transaction_number.' berhasil dibuat! Rincian pesanan telah dikirim ke email '.$transaction->customer_email.'.');
        }

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
        $cartItems = $this->getCheckoutItems($user, $user === null);
        $data = $request->validated();

        if (config('app.is_foodpick') && empty($data['resto_id']) && $cartItems->isNotEmpty()) {
            $data['resto_id'] = $cartItems->first()?->product?->user_id;
        }

        return $this->shippingCalculationService->calculate($data, $cartItems);
    }

    /**
     * Get checked cart items for user.
     *
     * @return Collection<int, CartItem>
     */
    protected function getCheckoutItems(?User $user, bool $isGuest = false): Collection
    {
        if (session()->has('buy_now_item')) {
            $buyNow = session('buy_now_item');
            $product = Product::with(['productPrice', 'productStock', 'images', 'tierPrices', 'variants.productPrice', 'variants.productStock', 'variants.options', 'variants.tierPrices'])->find($buyNow['product_id']);

            if (! $product) {
                session()->forget('buy_now_item');

                return new Collection;
            }

            $buyNowItemId = 'buy-now';
            $fakeItem = new CartItem([
                'id' => $buyNowItemId,
                'user_id' => $user?->id ?? null,
                'product_id' => $product->id,
                'product_variant_id' => $buyNow['product_variant_id'] ?? null,
                'quantity' => $buyNow['quantity'] ?? 1,
                'is_checked' => true,
                'note' => $buyNow['note'] ?? null,
                'selected_modifiers' => $buyNow['selected_modifiers'] ?? null,
            ]);
            $fakeItem->id = $buyNowItemId;

            $fakeItem->setRelation('product', $product);
            if (! empty($buyNow['product_variant_id'])) {
                $variant = $product->variants->firstWhere('id', $buyNow['product_variant_id']);
                $fakeItem->setRelation('productVariant', $variant);
            }

            return new Collection([$fakeItem]);
        }

        if (! $user || $isGuest) {
            $guestCart = session('guest_cart', []);
            $items = new Collection;
            foreach ($guestCart as $raw) {
                if (! ($raw['is_checked'] ?? true)) {
                    continue;
                }
                $product = Product::with([
                    'productPrice',
                    'productStock',
                    'images',
                    'tierPrices',
                    'variants.productPrice',
                    'variants.productStock',
                    'variants.options',
                    'variants.tierPrices',
                ])->find($raw['product_id']);

                if ($product) {
                    $guestItemId = (string) ($raw['id'] ?? ('guest-'.uniqid()));
                    $item = new CartItem([
                        'id' => $guestItemId,
                        'user_id' => $user?->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $raw['product_variant_id'] ?? null,
                        'quantity' => $raw['quantity'],
                        'is_checked' => true,
                        'note' => $raw['note'] ?? null,
                        'selected_modifiers' => $raw['selected_modifiers'] ?? null,
                    ]);
                    $item->id = $guestItemId;
                    $item->setRelation('product', $product);
                    if (! empty($raw['product_variant_id'])) {
                        $variant = $product->variants->firstWhere('id', $raw['product_variant_id']);
                        $item->setRelation('productVariant', $variant);
                    }
                    $items->push($item);
                }
            }

            return $items;
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
