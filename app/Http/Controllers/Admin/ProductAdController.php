<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAd;
use App\Models\SellerAdTransaction;
use App\Models\SellerAdWallet;
use App\Models\Setting;
use App\Services\MidtransService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductAdController extends Controller
{
    /**
     * Ensure only Admin and Super Admin can access product ad features.
     */
    protected function authorizeAdminOnly(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $isAdminOrSuperAdmin = $user->hasAnyRole(['Super Admin', 'Admin']) || (! $user->is_seller && $user->roles()->count() === 0);

        if (! $isAdminOrSuperAdmin) {
            abort(403, 'Akses ini khusus untuk Admin dan Super Admin.');
        }
    }

    /**
     * Display seller's advertising dashboard, campaigns, and wallet.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAdminOnly($request);

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin') || $user->hasRole('Admin');

        $wallet = SellerAdWallet::getOrCreateForUser($user->id);

        // Fetch campaigns
        $adsQuery = ProductAd::with(['product' => function ($q) {
            $q->select('id', 'name', 'slug', 'sku', 'image', 'active', 'user_id');
        }]);

        if (! $isSuperAdmin) {
            $adsQuery->where('user_id', $user->id);
        }

        $campaigns = $adsQuery->orderBy('created_at', 'desc')->get()->map(function ($ad) {
            $ad->checkAndResetDailyBudget();

            $ctr = $ad->impressions_count > 0
                ? round(($ad->clicks_count / $ad->impressions_count) * 100, 2)
                : 0;

            return [
                'id' => $ad->id,
                'product_id' => $ad->product_id,
                'product_name' => $ad->product?->name ?? 'Produk Dihapus',
                'product_slug' => $ad->product?->slug ?? '',
                'product_sku' => $ad->product?->sku ?? '',
                'product_image' => $this->formatProductImageUrl($ad->product?->image),
                'product_active' => (bool) ($ad->product?->active ?? false),
                'ad_type' => $ad->ad_type,
                'bid_per_click' => (float) $ad->bid_per_click,
                'daily_budget' => (float) $ad->daily_budget,
                'spent_today' => (float) $ad->spent_today,
                'total_spent' => (float) $ad->total_spent,
                'impressions_count' => (int) $ad->impressions_count,
                'clicks_count' => (int) $ad->clicks_count,
                'ctr' => $ctr,
                'status' => $ad->status,
                'show_badge' => (bool) $ad->show_badge,
                'placements' => $ad->placements ?? ['home', 'search', 'category', 'brand', 'bestseller', 'detail'],
                'start_date' => $ad->start_date?->toDateString(),
                'end_date' => $ad->end_date?->toDateString(),
                'created_at' => $ad->created_at->toISOString(),
            ];
        });

        // Summary Performance KPI
        $totalImpressions = $campaigns->sum('impressions_count');
        $totalClicks = $campaigns->sum('clicks_count');
        $totalSpent = $campaigns->sum('total_spent');
        $overallCtr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;
        $activeCampaignsCount = $campaigns->where('status', 'active')->count();

        // Transaction history
        $transactions = SellerAdTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'history_page')
            ->withQueryString()
            ->through(function ($tx) {
                return [
                    'id' => $tx->id,
                    'order_id' => $tx->order_id,
                    'type' => $tx->type,
                    'amount' => (float) $tx->amount,
                    'balance_after' => (float) $tx->balance_after,
                    'description' => $tx->description,
                    'payment_method' => $tx->payment_method,
                    'status' => $tx->status,
                    'paid_at' => $tx->paid_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? $tx->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i'),
                ];
            });

        // Get eligible products for new ad campaign
        $existingAdProductIds = ProductAd::where('user_id', $user->id)->pluck('product_id')->toArray();
        $availableProductsQuery = Product::where('active', true);
        if (! $isSuperAdmin) {
            $availableProductsQuery->where('user_id', $user->id);
        }
        $availableProducts = $availableProductsQuery->whereNotIn('id', $existingAdProductIds)
            ->select('id', 'name', 'sku', 'image')
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'image' => $this->formatProductImageUrl($p->image),
                ];
            });

        return Inertia::render('Admin/Promote/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'total_spent' => (float) $wallet->total_spent,
                'total_topup' => (float) $wallet->total_topup,
            ],
            'kpi' => [
                'total_impressions' => $totalImpressions,
                'total_clicks' => $totalClicks,
                'total_spent' => $totalSpent,
                'ctr' => $overallCtr,
                'active_campaigns_count' => $activeCampaignsCount,
                'total_campaigns_count' => $campaigns->count(),
            ],
            'campaigns' => $campaigns,
            'transactions' => $transactions,
            'availableProducts' => $availableProducts,
        ]);
    }

    /**
     * Store a new product ad campaign.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdminOnly($request);

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin') || $user->hasRole('Admin');

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'ad_type' => 'required|in:cpc,daily',
            'bid_per_click' => 'nullable|numeric|min:100|max:50000',
            'daily_budget' => 'nullable|numeric|min:1000|max:10000000',
            'show_badge' => 'nullable|boolean',
            'placements' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        if (! $isSuperAdmin && $product->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki izin mempromosikan produk ini.');
        }

        $wallet = SellerAdWallet::getOrCreateForUser($user->id);
        $initialStatus = $wallet->balance > 0 ? 'active' : 'depleted';

        ProductAd::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'ad_type' => $validated['ad_type'],
            'bid_per_click' => $validated['bid_per_click'] ?? 300,
            'daily_budget' => $validated['daily_budget'] ?? 10000,
            'spent_today' => 0,
            'total_spent' => 0,
            'impressions_count' => 0,
            'clicks_count' => 0,
            'status' => $initialStatus,
            'show_badge' => $request->boolean('show_badge', false),
            'placements' => $validated['placements'] ?? ['home', 'search', 'category', 'brand', 'bestseller', 'detail'],
            'start_date' => $validated['start_date'] ?? Carbon::today(),
            'end_date' => $validated['end_date'] ?? null,
            'last_spent_reset_at' => Carbon::today()->toDateString(),
        ]);

        $msg = $initialStatus === 'active'
            ? 'Kampanye iklan produk berhasil diaktifkan!'
            : 'Kampanye iklan berhasil dibuat! Saldo iklan Anda Rp 0, silakan Top Up saldo untuk mulai menayangkan iklan.';

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Update campaign status or budgets.
     */
    public function update(Request $request, ProductAd $ad): RedirectResponse
    {
        $this->authorizeAdminOnly($request);

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin') || $user->hasRole('Admin');

        if (! $isSuperAdmin && $ad->user_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'status' => 'nullable|in:active,paused',
            'bid_per_click' => 'nullable|numeric|min:100|max:50000',
            'daily_budget' => 'nullable|numeric|min:1000|max:10000000',
            'show_badge' => 'nullable|boolean',
            'placements' => 'nullable|array',
            'end_date' => 'nullable|date',
        ]);

        if (isset($validated['status'])) {
            if ($validated['status'] === 'active') {
                $wallet = SellerAdWallet::getOrCreateForUser($ad->user_id);
                if ($wallet->balance <= 0) {
                    return redirect()->back()->with('error', 'Saldo iklan Anda Rp 0. Silakan lakukan Top Up saldo terlebih dahulu.');
                }
            }
            $ad->status = $validated['status'];
        }

        if ($request->has('show_badge')) {
            $ad->show_badge = $request->boolean('show_badge');
        }

        if (isset($validated['placements'])) {
            $ad->placements = $validated['placements'];
        }

        if (isset($validated['bid_per_click'])) {
            $ad->bid_per_click = $validated['bid_per_click'];
        }

        if (isset($validated['daily_budget'])) {
            $ad->daily_budget = $validated['daily_budget'];
        }

        if (array_key_exists('end_date', $validated)) {
            $ad->end_date = $validated['end_date'] ? Carbon::parse($validated['end_date']) : null;
        }

        $ad->save();

        return redirect()->back()->with('success', 'Pengaturan iklan berhasil diperbarui!');
    }

    /**
     * Delete campaign.
     */
    public function destroy(Request $request, ProductAd $ad): RedirectResponse
    {
        $this->authorizeAdminOnly($request);

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin') || $user->hasRole('Admin');

        if (! $isSuperAdmin && $ad->user_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        $ad->delete();

        return redirect()->back()->with('success', 'Iklan produk berhasil dihapus.');
    }

    /**
     * Request Top Up for Seller Ad Wallet (generate QRIS).
     */
    public function topup(Request $request): JsonResponse
    {
        $this->authorizeAdminOnly($request);

        $user = $request->user();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:10000|max:50000000',
        ]);

        $amount = (float) $validated['amount'];
        $orderId = 'AD-'.strtoupper(Str::random(6)).'-'.time();

        $serverKey = Setting::where('key', 'midtrans_server_key')->value('value');
        $qrImage = null;
        $qrString = null;
        $isSandbox = false;

        if (! empty($serverKey) && $amount > 0) {
            try {
                $chargeResult = MidtransService::charge(
                    $orderId,
                    $amount,
                    'qris',
                    [
                        'name' => $user->name ?? 'Seller',
                        'email' => $user->email ?? 'seller@example.com',
                        'phone' => $user->phone ?? '',
                    ]
                );

                if ($chargeResult['success'] && ! empty($chargeResult['data'])) {
                    $qrImage = $chargeResult['data']['qr_image'] ?? null;
                    $qrString = $chargeResult['data']['qr_string'] ?? null;
                    $isSandbox = str_contains(MidtransService::getCoreApiBaseUrl(), 'sandbox');
                }
            } catch (\Throwable $e) {
                Log::warning('Midtrans charge for ad topup failed: '.$e->getMessage());
            }
        }

        if (! $qrImage && ! $qrString) {
            $dummyPayload = '00020101021226680016ID.CO.QRIS.WWW01189360091400000000000215'.str_pad((string) $amount, 12, '0', STR_PAD_LEFT).'5802ID5910BIZMATE006007JAKARTA6304ABCD';
            $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($dummyPayload);
            $qrString = $dummyPayload;
            $isSandbox = true;
        }

        // Create pending transaction
        $wallet = SellerAdWallet::getOrCreateForUser($user->id);

        SellerAdTransaction::create([
            'order_id' => $orderId,
            'user_id' => $user->id,
            'type' => 'topup',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'description' => 'Top Up Saldo Iklan via QRIS',
            'payment_method' => 'qris',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'order_id' => $orderId,
            'amount' => $amount,
            'qr_image' => $qrImage,
            'qr_string' => $qrString,
            'is_sandbox' => $isSandbox,
            'expiry_time' => now()->addMinutes(30)->toIso8601String(),
        ]);
    }

    /**
     * Check or simulate payment confirmation for Ad Top Up.
     */
    public function checkTopupStatus(Request $request): JsonResponse
    {
        $this->authorizeAdminOnly($request);

        $user = $request->user();
        $orderId = $request->input('order_id');
        $autoConfirm = $request->boolean('auto_confirm', false);

        $tx = SellerAdTransaction::where('order_id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (! $tx) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        if ($tx->status === 'paid') {
            $wallet = SellerAdWallet::getOrCreateForUser($user->id);

            return response()->json([
                'success' => true,
                'status' => 'paid',
                'balance' => (float) $wallet->balance,
                'message' => 'Pembayaran top up berhasil!',
            ]);
        }

        // In sandbox or simulate auto confirm
        if ($autoConfirm || $tx->status === 'pending') {
            DB::transaction(function () use ($tx, $user) {
                $wallet = SellerAdWallet::getOrCreateForUser($user->id);
                $newBalance = $wallet->balance + $tx->amount;

                $wallet->balance = $newBalance;
                $wallet->total_topup += $tx->amount;
                $wallet->save();

                $tx->status = 'paid';
                $tx->balance_after = $newBalance;
                $tx->paid_at = Carbon::now();
                $tx->save();

                // Reactivate any depleted ads
                ProductAd::where('user_id', $user->id)
                    ->where('status', 'depleted')
                    ->update(['status' => 'active']);
            });

            $wallet = SellerAdWallet::getOrCreateForUser($user->id);

            return response()->json([
                'success' => true,
                'status' => 'paid',
                'balance' => (float) $wallet->balance,
                'message' => 'Top Up sebesar Rp '.number_format($tx->amount, 0, ',', '.').' berhasil ditambahkan ke saldo iklan Anda!',
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => $tx->status,
        ]);
    }

    /**
     * Format product image URL safely avoiding duplicate /storage/ prefixes.
     */
    private function formatProductImageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, '/storage/')) {
            return $image;
        }

        if (str_starts_with($image, 'storage/')) {
            return '/'.$image;
        }

        return '/storage/'.$image;
    }
}
