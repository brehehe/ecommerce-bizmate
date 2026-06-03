<?php

namespace App\Http\Middleware;

use App\Models\CartItem;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\ProductStock;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        $operationalHours = [];

        $refundPointsEnabled = false;
        $refundTransferDays = '3-5';
        $refundMinAmountTransfer = 0;
        $refundMinAmountPoints = 0;
        $refundTermsTransfer = '';
        $refundTermsPoints = '';

        try {
            if (Schema::hasTable('settings')) {
                $primaryColor = Setting::where('key', 'primary_color')->value('value') ?? $primaryColor;
                $secondaryColor = Setting::where('key', 'secondary_color')->value('value') ?? $secondaryColor;
                $taxEnabled = Setting::where('key', 'tax_enabled')->value('value') === '1';
                $taxPercentage = Setting::where('key', 'tax_percentage')->value('value') ?? 0;
                $storeName = Setting::where('key', 'store_name')->value('value') ?? $storeName;
                $storeLogo = Setting::where('key', 'store_logo')->value('value');
                $storeIcon = Setting::where('key', 'store_icon')->value('value');
                $setupTourCompleted = Setting::where('key', 'setup_tour_completed')->value('value') === '1';
                $cartButtonStyle = Setting::where('key', 'storefront_cart_button_style')->value('value') ?? 'button';

                $coinsEnabled = Setting::where('key', 'coins_enabled')->value('value') === '1';
                $coinConversionRate = (float) (Setting::where('key', 'coin_conversion_rate')->value('value') ?? 1);
                $coinEarningMethod = Setting::where('key', 'coin_earning_method')->value('value') ?? 'proportional';
                $coinEarningRateRupiah = (float) (Setting::where('key', 'coin_earning_rate_rupiah')->value('value') ?? 1000);
                $coinEarningRateCoins = (float) (Setting::where('key', 'coin_earning_rate_coins')->value('value') ?? 1);

                $tiersVal = Setting::where('key', 'coin_earning_tiers')->value('value');
                $coinEarningTiers = $tiersVal ? json_decode($tiersVal, true) : [];
                if (! is_array($coinEarningTiers)) {
                    $coinEarningTiers = [];
                }

                $coinMinPurchaseRedeem = (float) (Setting::where('key', 'coin_min_purchase_redeem')->value('value') ?? 0);
                $coinMaxRedeemPerTxn = (float) (Setting::where('key', 'coin_max_redeem_per_txn')->value('value') ?? 50000);
                $coinMaxRedeemPercentage = (float) (Setting::where('key', 'coin_max_redeem_percentage')->value('value') ?? 100);
                $coinTermsConditions = Setting::where('key', 'coin_terms_conditions')->value('value') ?? '';

                $holidayMode = Setting::where('key', 'holiday_mode')->value('value') === '1';
                $alwaysOpen = Setting::where('key', 'always_open')->value('value') !== '0'; // default true if not set
                $opsHoursVal = Setting::where('key', 'operational_hours')->value('value');

                $refundPointsEnabled = Setting::where('key', 'refund_points_enabled')->value('value') === '1';
                $refundTransferDays = Setting::where('key', 'refund_transfer_days')->value('value') ?? '3-5';
                $refundMinAmountTransfer = (float) (Setting::where('key', 'refund_min_amount_transfer')->value('value') ?? 0);
                $refundMinAmountPoints = (float) (Setting::where('key', 'refund_min_amount_points')->value('value') ?? 0);
                $refundTermsTransfer = Setting::where('key', 'refund_terms_transfer')->value('value') ?? '';
                $refundTermsPoints = Setting::where('key', 'refund_terms_points')->value('value') ?? '';

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
                'user' => $request->user(),
            ],
            'cartCount' => $request->user() ? CartItem::where('user_id', $request->user()->id)->sum('quantity') : 0,
            'chatUnreadCount' => $request->user() ? ChatMessage::whereHas('chat', fn ($q) => $q->where('user_id', $request->user()->id))->where('sender_type', 'admin')->where('is_read', false)->count() : 0,
            'adminChatUnreadCount' => $request->user() ? ChatMessage::where('sender_type', 'user')->where('is_read', false)->count() : 0,
            'theme' => [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
            ],
            'settings' => [
                'tax_enabled' => $taxEnabled,
                'tax_percentage' => (float) $taxPercentage,
                'store_name' => $storeName,
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

                'refund_points_enabled' => $refundPointsEnabled,
                'refund_transfer_days' => $refundTransferDays,
                'refund_min_amount_transfer' => $refundMinAmountTransfer,
                'refund_min_amount_points' => $refundMinAmountPoints,
                'refund_terms_transfer' => $refundTermsTransfer,
                'refund_terms_points' => $refundTermsPoints,
            ],
            'adminNotifications' => $request->user() && ! $request->user()->hasRole('Customer') ? [
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
            ] : null,
            'customerNotifications' => $request->user() ? Notification::where('user_id', $request->user()->id)
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
                ]) : [],
        ];
    }
}
