<?php

namespace App\Http\Middleware;

use App\Models\CartItem;
use App\Models\ChatMessage;
use App\Models\ChatSticker;
use App\Models\Notification;
use App\Models\ProductStock;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\SocialMedia;
use App\Models\Transaction;
use App\Services\KomerceService;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $primaryColor = '#0c4cb4';
        $secondaryColor = '#fa7315';
        $taxEnabled = false;
        $taxPercentage = 0;
        $storeName = config('app.name');
        $storeAppName = config('app.name');
        $storeLogo = null;

        $setupTourCompleted = false;
        $cartButtonStyle = 'button';

        $coinsEnabled = false;
        $coinConversionRate = 1;
        $coinEarningMethod = 'proportional';
        $coinEarningRateRupiah = 1000;
        $coinEarningRateCoins = 1;
        $coinEarningTiers = [];
        $coinMinPurchaseRedeem = 0;
        $coinMaxRedeemPerTxn = 50000;
        $coinMaxRedeemPercentage = 100;
        $coinTermsConditions = '';
        $storeLogo = null;
        $storeIcon = null;

        $holidayMode = false;
        $alwaysOpen = true;

        // Driven by CHECKOUT_LOCKED / CHECKOUT_LOCKED_MESSAGE in .env
        $checkoutLocked = (bool) config('app.checkout_locked', false);
        $checkoutLockedMessage = config('app.checkout_locked_message', 'Checkout sedang dinonaktifkan sementara. Silakan coba lagi nanti.');
        $pwaInstallEnabled = (bool) config('app.pwa_install_enabled', true);
        $pickupEnabled = (bool) config('app.pickup_enabled', true);
        $operationalHours = [];

        $refundPointsEnabled = false;
        $refundTransferDays = '3-5';
        $refundMinAmountTransfer = 0;
        $refundMinAmountPoints = 0;
        $refundTermsTransfer = '';
        $refundTermsPoints = '';

        $shippingDeliveryEnabled = false;
        $paymentApiEnabled = false;
        $paymentApiAdminFee = 0;
        $qrislyApiEnabled = false;
        $qrislyApiAdminFee = 0;
        $komerceDeliveryUrl = 'https://api-sandbox.collaborator.komerce.id/api/v1/';
        $midtransApiEnabled = false;
        $midtransClientKey = '';
        $midtransSnapUrl = 'https://app.sandbox.midtrans.com';

        $selfPickupEnabled = false;
        $selfPickupFee = 0;
        $storeCourierEnabled = false;
        $storeCourierType = 'flat';
        $storeCourierFlatFee = 0;
        $storeCourierPerKmFee = 0;
        $storeCourierMaxRadius = 50;
        $storeCourierRoundUp = false;
        $storeCourierTieredRates = [];

        $storeAddress = '';
        $storeProvince = '';
        $storeRegency = '';
        $storeDistrict = '';
        $storeVillage = '';
        $storePostalCode = '';
        $storeLatitude = '';
        $storeLongitude = '';

        $storeEmail = '';
        $storePhone = '';
        $storeWhatsapp = '';
        $storeInstagram = '';
        $storeTiktok = '';
        $storeDescription = '';

        $socialMediaLinks = [];
        $chatStickers = [];

        try {
            if (Schema::hasTable('settings')) {
                $settings = Setting::pluck('value', 'key')->all();

                $primaryColor = $settings['primary_color'] ?? $primaryColor;
                $secondaryColor = $settings['secondary_color'] ?? $secondaryColor;
                $taxEnabled = ($settings['tax_enabled'] ?? null) === '1';
                $taxPercentage = $settings['tax_percentage'] ?? 0;
                $storeName = $settings['store_name'] ?? $storeName;
                $storeAppName = $settings['store_app_name'] ?? $storeName;
                $storeLogo = $settings['store_logo'] ?? null;
                $storeIcon = $settings['store_icon'] ?? null;
                $setupTourCompleted = ($settings['setup_tour_completed'] ?? null) === '1';
                $cartButtonStyle = $settings['storefront_cart_button_style'] ?? 'button';

                $coinsEnabled = ($settings['coins_enabled'] ?? null) === '1';
                $coinConversionRate = (float) ($settings['coin_conversion_rate'] ?? 1);
                $coinEarningMethod = $settings['coin_earning_method'] ?? 'proportional';
                $coinEarningRateRupiah = (float) ($settings['coin_earning_rate_rupiah'] ?? 1000);
                $coinEarningRateCoins = (float) ($settings['coin_earning_rate_coins'] ?? 1);

                $tiersVal = $settings['coin_earning_tiers'] ?? null;
                $coinEarningTiers = $tiersVal ? json_decode($tiersVal, true) : [];
                if (! is_array($coinEarningTiers)) {
                    $coinEarningTiers = [];
                }

                $coinMinPurchaseRedeem = (float) ($settings['coin_min_purchase_redeem'] ?? 0);
                $coinMaxRedeemPerTxn = (float) ($settings['coin_max_redeem_per_txn'] ?? 50000);
                $coinMaxRedeemPercentage = (float) ($settings['coin_max_redeem_percentage'] ?? 100);
                $coinTermsConditions = $settings['coin_terms_conditions'] ?? '';

                $holidayMode = ($settings['holiday_mode'] ?? null) === '1';
                $alwaysOpen = ($settings['always_open'] ?? null) !== '0'; // default true if not set
                $opsHoursVal = $settings['operational_hours'] ?? null;

                $refundPointsEnabled = ($settings['refund_points_enabled'] ?? null) === '1';
                $refundTransferDays = $settings['refund_transfer_days'] ?? '3-5';
                $refundMinAmountTransfer = (float) ($settings['refund_min_amount_transfer'] ?? 0);
                $refundMinAmountPoints = (float) ($settings['refund_min_amount_points'] ?? 0);
                $refundTermsTransfer = $settings['refund_terms_transfer'] ?? '';
                $refundTermsPoints = $settings['refund_terms_points'] ?? '';

                $shippingDeliveryEnabled = config('app.logistic_enabled', true) && ($settings['shipping_delivery_enabled'] ?? null) === '1';
                $paymentApiEnabled = ($settings['payment_api_enabled'] ?? null) === '1';
                $paymentApiAdminFee = (float) ($settings['payment_api_admin_fee'] ?? 0);
                $qrislyApiEnabled = ($settings['qrisly_api_enabled'] ?? null) === '1';
                $qrislyApiAdminFee = (float) ($settings['qrisly_api_admin_fee'] ?? 0);
                $komerceDeliveryUrl = KomerceService::getSetting('komerce_delivery_url', 'app.rajaongkir.delivery_url');
                $midtransApiEnabled = config('app.midtrans_enabled', true) && ($settings['midtrans_api_enabled'] ?? null) === '1';
                $midtransClientKey = $settings['midtrans_client_key'] ?? '';
                $midtransSnapUrl = $settings['midtrans_snap_url'] ?? 'https://app.sandbox.midtrans.com';

                $selfPickupEnabled = ($settings['self_pickup_enabled'] ?? null) === '1';
                $selfPickupFee = (float) ($settings['self_pickup_fee'] ?? 0);
                $storeCourierEnabled = ($settings['store_courier_enabled'] ?? null) === '1';
                $storeCourierType = $settings['store_courier_type'] ?? 'flat';
                $storeCourierFlatFee = (float) ($settings['store_courier_flat_fee'] ?? 0);
                $storeCourierPerKmFee = (float) ($settings['store_courier_per_km_fee'] ?? 0);
                $storeCourierMaxRadius = (float) ($settings['store_courier_max_radius'] ?? 50);
                $storeCourierRoundUp = ($settings['store_courier_round_up'] ?? null) === '1';

                $tieredRatesVal = $settings['store_courier_tiered_rates'] ?? null;
                $storeCourierTieredRates = $tieredRatesVal ? json_decode($tieredRatesVal, true) : [];
                if (! is_array($storeCourierTieredRates)) {
                    $storeCourierTieredRates = [];
                }

                $storeAddress = $settings['address'] ?? '';
                $storeProvince = $settings['province_name'] ?? '';
                $storeRegency = $settings['regency_name'] ?? '';
                $storeDistrict = $settings['district_name'] ?? '';
                $storeVillage = $settings['village_name'] ?? '';
                $storePostalCode = $settings['postal_code'] ?? '';
                $storeLatitude = $settings['latitude'] ?? '';
                $storeLongitude = $settings['longitude'] ?? '';

                $storeEmail = $settings['store_email'] ?? '';
                $storePhone = $settings['store_phone'] ?? '';
                $storeWhatsapp = $settings['store_whatsapp'] ?? '';
                $storeInstagram = $settings['store_instagram'] ?? '';
                $storeTiktok = $settings['store_tiktok'] ?? '';
                $storeDescription = $settings['store_description'] ?? '';

                $operationalHours = $opsHoursVal ? json_decode($opsHoursVal, true) : [
                    'monday' => ['active' => true, 'open' => '09:00', 'close' => '17:00'],
                    'tuesday' => ['active' => true, 'open' => '09:00', 'close' => '17:00'],
                    'wednesday' => ['active' => true, 'open' => '09:00', 'close' => '17:00'],
                    'thursday' => ['active' => true, 'open' => '09:00', 'close' => '17:00'],
                    'friday' => ['active' => true, 'open' => '09:00', 'close' => '17:00'],
                    'saturday' => ['active' => true, 'open' => '09:00', 'close' => '15:00'],
                    'sunday' => ['active' => false, 'open' => '09:00', 'close' => '12:00'],
                ];
                if (! is_array($operationalHours)) {
                    $operationalHours = [];
                }
            }

            if (Schema::hasTable('social_media')) {
                $socialMediaLinks = SocialMedia::where('is_active', true)
                    ->orderBy('order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'platform' => $s->platform,
                        'label' => $s->label,
                        'url' => $s->url,
                        'icon' => $s->icon,
                    ])
                    ->toArray();
            }

            if (Schema::hasTable('chat_stickers')) {
                $chatStickers = ChatSticker::where('is_active', true)
                    ->orderBy('order')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'category' => $s->category,
                        'url' => $s->image_url,
                    ])
                    ->toArray();
            }
        } catch (\Throwable $e) {
            // Fallback when database is not ready
        }


        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'id' => \Str::uuid()->toString(),
            ],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone_number' => $request->user()->phone_number,
                    'gender' => $request->user()->gender,
                    'birth_date' => $request->user()->birth_date,
                    'avatar' => $request->user()->avatar,
                    'coins_balance' => $request->user()->coins_balance,
                    'roles' => $request->user()->roles->map(fn ($role) => [
                        'name' => $role->name,
                    ]),
                ] : null,
            ],
            'cartCount' => $request->user() ? CartItem::where('user_id', $request->user()->id)->count() : 0,
            'chatUnreadCount' => $request->user() ? ChatMessage::whereHas('chat', fn ($q) => $q->where('user_id', $request->user()->id))->where('sender_type', 'admin')->where('is_read', false)->count() : 0,
            'adminChatUnreadCount' => $request->user() ? ChatMessage::where('sender_type', 'user')->where('is_read', false)->count() : 0,
            'theme' => [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
            ],
            'settings' => [
                'membership_enabled' => (bool) config('app.membership_enabled', true),
                'midtrans_enabled' => (bool) config('app.midtrans_enabled', true),
                'logistic_enabled' => (bool) config('app.logistic_enabled', true),
                'enable_3d_models' => (bool) config('app.enable_3d_models', true),
                'tax_enabled' => $taxEnabled,
                'tax_percentage' => (float) $taxPercentage,
                'store_name' => $storeName,
                'store_app_name' => $storeAppName,
                'store_logo' => $storeLogo,
                'store_icon' => $storeIcon,
                'setup_tour_completed' => $setupTourCompleted,
                'storefront_cart_button_style' => $cartButtonStyle,

                'coins_enabled' => $coinsEnabled,
                'coin_conversion_rate' => $coinConversionRate,
                'coin_earning_method' => $coinEarningMethod,
                'coin_earning_rate_rupiah' => $coinEarningRateRupiah,
                'coin_earning_rate_coins' => $coinEarningRateCoins,
                'coin_earning_tiers' => $coinEarningTiers,
                'coin_min_purchase_redeem' => $coinMinPurchaseRedeem,
                'coin_max_redeem_per_txn' => $coinMaxRedeemPerTxn,
                'coin_max_redeem_percentage' => $coinMaxRedeemPercentage,
                'coin_terms_conditions' => $coinTermsConditions,

                'holiday_mode' => $holidayMode,
                'always_open' => $alwaysOpen,
                'operational_hours' => $operationalHours,

                'checkout_locked' => $checkoutLocked,
                'checkout_locked_message' => $checkoutLockedMessage,
                'pwa_install_enabled' => $pwaInstallEnabled,
                'pickup_enabled' => $pickupEnabled,

                'refund_points_enabled' => $refundPointsEnabled,
                'refund_transfer_days' => $refundTransferDays,
                'refund_min_amount_transfer' => $refundMinAmountTransfer,
                'refund_min_amount_points' => $refundMinAmountPoints,
                'refund_terms_transfer' => $refundTermsTransfer,
                'refund_terms_points' => $refundTermsPoints,

                'shipping_delivery_enabled' => $shippingDeliveryEnabled,
                'payment_api_enabled' => $paymentApiEnabled,
                'payment_api_admin_fee' => $paymentApiAdminFee,
                'qrisly_api_enabled' => $qrislyApiEnabled,
                'qrisly_api_admin_fee' => $qrislyApiAdminFee,
                'komerce_delivery_url' => $komerceDeliveryUrl,
                'midtrans_api_enabled' => $midtransApiEnabled,
                'midtrans_client_key' => $midtransClientKey,
                'midtrans_snap_url' => $midtransSnapUrl,

                'self_pickup_enabled' => $selfPickupEnabled,
                'self_pickup_fee' => $selfPickupFee,
                'store_courier_enabled' => $storeCourierEnabled,
                'store_courier_type' => $storeCourierType,
                'store_courier_flat_fee' => $storeCourierFlatFee,
                'store_courier_per_km_fee' => $storeCourierPerKmFee,
                'store_courier_max_radius' => $storeCourierMaxRadius,
                'store_courier_round_up' => $storeCourierRoundUp,
                'store_courier_tiered_rates' => $storeCourierTieredRates,

                'store_address' => $storeAddress,
                'store_province' => $storeProvince,
                'store_regency' => $storeRegency,
                'store_district' => $storeDistrict,
                'store_village' => $storeVillage,
                'store_postal_code' => $storePostalCode,
                'store_latitude' => $storeLatitude,
                'store_longitude' => $storeLongitude,

                'store_email' => $storeEmail,
                'store_phone' => $storePhone,
                'store_whatsapp' => $storeWhatsapp,
                'store_instagram' => $storeInstagram,
                'store_tiktok' => $storeTiktok,
                'store_description' => $storeDescription,
            ],
            'socialMediaLinks' => $socialMediaLinks,
            'chatStickers' => $chatStickers,
            'adminNotifications' => $request->user() && ! $request->user()->hasRole('Customer')
                ? Inertia::defer(fn () => [
                    'lowStockCount' => ProductStock::where('is_unlimited', false)
                        ->where('stock', '>', 0)
                        ->whereColumn('stock', '<=', 'min_stock')
                        ->count(),
                    'outOfStockCount' => ProductStock::where('is_unlimited', false)
                        ->where('stock', '<=', 0)
                        ->count(),
                    'lowStockItems' => ProductStock::with(['product', 'variant.options'])
                        ->where('is_unlimited', false)
                        ->where('stock', '>', 0)
                        ->whereColumn('stock', '<=', 'min_stock')
                        ->orderBy('stock', 'asc')
                        ->limit(5)
                        ->get()
                        ->map(fn ($ps) => [
                            'id' => $ps->id,
                            'product_id' => $ps->product_id,
                            'name' => $ps->product ? $ps->product->name.($ps->variant && $ps->variant->options->count() > 0 ? ' ('.$ps->variant->options->pluck('name')->implode(', ').')' : '') : 'Unknown Product',
                            'stock' => $ps->stock,
                            'min_stock' => $ps->min_stock,
                        ]),
                    'outOfStockItems' => ProductStock::with(['product', 'variant.options'])
                        ->where('is_unlimited', false)
                        ->where('stock', '<=', 0)
                        ->limit(5)
                        ->get()
                        ->map(fn ($ps) => [
                            'id' => $ps->id,
                            'product_id' => $ps->product_id,
                            'name' => $ps->product ? $ps->product->name.($ps->variant && $ps->variant->options->count() > 0 ? ' ('.$ps->variant->options->pluck('name')->implode(', ').')' : '') : 'Unknown Product',
                            'stock' => $ps->stock,
                        ]),
                    'transactionCounts' => [
                        'belum_bayar' => Transaction::where('status', 'belum_bayar')->count(),
                        'menunggu' => Transaction::where('status', 'menunggu')->count(),
                        'diproses' => Transaction::where('status', 'diproses')->count(),
                    ],
                    'returnCounts' => [
                        'menunggu_review' => ReturnRequest::where('status', 'menunggu_review')->count(),
                    ],
                    'refundCounts' => [
                        'menunggu_konfirmasi' => RefundRequest::where('status', 'menunggu_konfirmasi')->count(),
                    ],
                    'notifications' => Notification::whereNull('user_id')
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get()
                        ->map(fn ($n) => [
                            'id' => $n->id,
                            'title' => $n->title,
                            'message' => $n->message,
                            'type' => $n->type,
                            'url' => $n->url,
                            'is_read' => $n->is_read,
                            'created_at' => $n->created_at->diffForHumans(),
                            'time_raw' => $n->created_at->toIso8601String(),
                        ]),
                ]) : null,
            'customerNotifications' => $request->user()
                ? Inertia::defer(fn () => Notification::where('user_id', $request->user()->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->title,
                        'message' => $n->message,
                        'type' => $n->type,
                        'url' => $n->url,
                        'is_read' => $n->is_read,
                        'created_at' => $n->created_at->diffForHumans(),
                        'time_raw' => $n->created_at->toIso8601String(),
                    ])
                    ->toArray())
                : [],
            'membershipInfo' => $request->user() && $request->user()->hasRole('Customer')
                ? Inertia::defer(fn () => app(MembershipService::class)->getMembershipInfoForFrontend($request->user()))
                : null,
        ];
    }
}
