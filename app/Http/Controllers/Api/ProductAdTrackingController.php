<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAd;
use App\Models\ProductAdClick;
use App\Models\SellerAdTransaction;
use App\Models\SellerAdWallet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductAdTrackingController extends Controller
{
    /**
     * Record impression for promoted products.
     */
    public function recordImpressions(Request $request): JsonResponse
    {
        $productIds = $request->input('product_ids', []);
        if (empty($productIds) || ! is_array($productIds)) {
            return response()->json(['success' => true]);
        }

        ProductAd::active()
            ->whereIn('product_id', $productIds)
            ->increment('impressions_count');

        return response()->json(['success' => true]);
    }

    /**
     * Record a click on a promoted product and atomically deduct CPC cost with anti-spam protection.
     */
    public function recordClick(Request $request): JsonResponse
    {
        $productId = $request->input('product_id');
        if (! $productId) {
            return response()->json(['success' => false, 'message' => 'Product ID is required.'], 400);
        }

        $ad = ProductAd::active()->where('product_id', $productId)->first();
        if (! $ad) {
            return response()->json(['success' => true, 'billed' => false]);
        }

        $ad->checkAndResetDailyBudget();

        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        $user = $request->user();

        // Anti-click fraud: check if same IP clicked this ad in the last 60 minutes
        $oneHourAgo = Carbon::now()->subMinutes(60);
        $recentClick = ProductAdClick::where('product_ad_id', $ad->id)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', $oneHourAgo)
            ->where('cost', '>', 0)
            ->exists();

        $costToBill = 0;
        if (! $recentClick && $ad->ad_type === 'cpc') {
            $costToBill = (float) $ad->bid_per_click;
        }

        DB::transaction(function () use ($ad, $productId, $user, $ipAddress, $userAgent, $costToBill) {
            $wallet = SellerAdWallet::getOrCreateForUser($ad->user_id);

            // If seller has enough balance and cost > 0
            if ($costToBill > 0 && $wallet->balance >= $costToBill) {
                $newBalance = $wallet->balance - $costToBill;
                $wallet->balance = $newBalance;
                $wallet->total_spent += $costToBill;
                $wallet->save();

                $productName = Product::where('id', $productId)->value('name') ?? 'Produk';

                SellerAdTransaction::create([
                    'user_id' => $ad->user_id,
                    'type' => 'click_cost',
                    'amount' => $costToBill,
                    'balance_after' => $newBalance,
                    'description' => "Biaya Klik Iklan Produk: {$productName}",
                    'status' => 'paid',
                    'paid_at' => Carbon::now(),
                ]);

                $ad->spent_today += $costToBill;
                $ad->total_spent += $costToBill;
                $ad->clicks_count += 1;

                // Check daily budget limit
                if ($ad->daily_budget > 0 && $ad->spent_today >= $ad->daily_budget) {
                    $ad->status = 'depleted';
                }

                // Check if wallet exhausted
                if ($newBalance <= 0) {
                    ProductAd::where('user_id', $ad->user_id)
                        ->where('status', 'active')
                        ->update(['status' => 'depleted']);
                }

                $ad->save();

                ProductAdClick::create([
                    'product_ad_id' => $ad->id,
                    'product_id' => $productId,
                    'user_id' => $user?->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'cost' => $costToBill,
                ]);
            } else {
                // Free click (repeat click within 1 hour or daily budget reached)
                $ad->clicks_count += 1;
                $ad->save();

                ProductAdClick::create([
                    'product_ad_id' => $ad->id,
                    'product_id' => $productId,
                    'user_id' => $user?->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'cost' => 0,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'billed' => $costToBill > 0,
            'cost' => $costToBill,
        ]);
    }
}
