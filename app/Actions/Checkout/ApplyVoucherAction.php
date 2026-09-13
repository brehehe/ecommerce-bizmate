<?php

namespace App\Actions\Checkout;

use App\Models\Promotion;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class ApplyVoucherAction
{
    /**
     * Resolve voucher application on subtotal.
     *
     * @return array{valid: bool, message: string, promotion: mixed, discount_type: ?string, discount_value: float, discount_amount: float, shipping_discount: float, is_points_voucher?: bool, voucher_points?: int}
     */
    public function execute(string $code, float $shippingFee, float $subtotal, ?Collection $cartItems = null, ?User $user = null): array
    {
        $codes = array_filter(array_map('trim', explode(',', $code)));
        $promotions = Promotion::whereIn('code', $codes)
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        if ($promotions->isEmpty()) {
            return [
                'valid' => false,
                'message' => 'Kode voucher tidak valid atau sudah kedaluwarsa.',
                'promotion' => null,
                'discount_type' => null,
                'discount_value' => 0.0,
                'discount_amount' => 0.0,
                'shipping_discount' => 0.0,
                'is_points_voucher' => false,
                'voucher_points' => 0,
            ];
        }

        $totalDiscountAmount = 0;
        $totalShippingDiscount = 0;
        $resolvedPromotions = [];
        $appliedDiscountType = null;
        $appliedDiscountValue = null;
        $isPointsVoucher = false;
        $voucherPoints = 0;

        $targetUser = $user ?? auth()->user();

        foreach ($promotions as $promo) {
            if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit) {
                return [
                    'valid' => false,
                    'message' => "Voucher {$promo->code} sudah mencapai batas penggunaan.",
                    'promotion' => null,
                    'discount_type' => null,
                    'discount_value' => 0.0,
                    'discount_amount' => 0.0,
                    'shipping_discount' => 0.0,
                    'is_points_voucher' => false,
                    'voucher_points' => 0,
                ];
            }

            $maxUsesPerUser = $promo->settings['max_uses_per_user'] ?? null;
            if ($maxUsesPerUser && $targetUser) {
                $userUses = Transaction::where('user_id', $targetUser->id)
                    ->where('status', '!=', 'batal')
                    ->where(function ($q) use ($promo) {
                        $q->where('voucher_code', $promo->code)
                            ->orWhere('applied_voucher_code', $promo->code);
                    })
                    ->count();

                if ($userUses >= $maxUsesPerUser) {
                    return [
                        'valid' => false,
                        'message' => "Batas penggunaan voucher \"{$promo->code}\" per user telah tercapai.",
                        'promotion' => null,
                        'discount_type' => null,
                        'discount_value' => 0.0,
                        'discount_amount' => 0.0,
                        'shipping_discount' => 0.0,
                        'is_points_voucher' => false,
                        'voucher_points' => 0,
                    ];
                }
            }

            if ($promo->min_purchase && $subtotal < (float) $promo->min_purchase) {
                return [
                    'valid' => false,
                    'message' => "Total belanja belum mencapai minimum untuk voucher {$promo->code} (Rp ".number_format((float) $promo->min_purchase, 0, ',', '.').').',
                    'promotion' => null,
                    'discount_type' => null,
                    'discount_value' => 0.0,
                    'discount_amount' => 0.0,
                    'shipping_discount' => 0.0,
                    'is_points_voucher' => false,
                    'voucher_points' => 0,
                ];
            }

            if (! empty($promo->settings['is_points_voucher'])) {
                $isPointsVoucher = true;
                $hasPreviousTx = $targetUser ? Transaction::where('user_id', $targetUser->id)->where('status', '!=', 'batal')->exists() : false;
                $voucherPoints = $hasPreviousTx
                    ? (int) ($promo->settings['points_existing_user'] ?? 0)
                    : (int) ($promo->settings['points_new_user'] ?? 0);
            }

            $discountType = $promo->discount_type;
            $discountValue = (float) $promo->discount_value;
            $maxDiscount = $promo->max_discount ? (float) $promo->max_discount : null;

            if ($promo->type === 'voucher_gratis_ongkir') {
                $shipDisc = $discountType === 'percentage'
                    ? round(($discountValue / 100) * $shippingFee, 2)
                    : ($discountValue > 0 ? min($discountValue, $shippingFee) : $shippingFee);

                if ($maxDiscount) {
                    $shipDisc = min($shipDisc, $maxDiscount);
                }
                $totalShippingDiscount += min($shipDisc, $shippingFee);
            } else {
                $calcSubtotal = $subtotal;
                if (isset($promo->settings['can_stack_with_promos']) && $promo->settings['can_stack_with_promos'] === false && $cartItems) {
                    $calcSubtotal = 0;
                    foreach ($cartItems as $item) {
                        $prod = $item->product;
                        $variant = $item->productVariant;
                        $isItemPromo = $variant ? ($variant->is_promo ?? false) : ($prod->is_promo ?? false);
                        if (! $isItemPromo) {
                            $pPrice = $variant ? ($variant->productPrice?->price ?? 0) : ($prod->productPrice?->price ?? 0);
                            $calcSubtotal += (float) $pPrice * $item->quantity;
                        }
                    }
                }

                $disc = $discountType === 'percentage'
                    ? round(($discountValue / 100) * $calcSubtotal, 2)
                    : min($discountValue, $calcSubtotal);

                if ($maxDiscount) {
                    $disc = min($disc, $maxDiscount);
                }
                $totalDiscountAmount += min($disc, $calcSubtotal);
                $appliedDiscountType = $discountType;
                $appliedDiscountValue = $discountValue;
            }

            $resolvedPromotions[] = $promo;
        }

        return [
            'valid' => true,
            'message' => 'Voucher berhasil diterapkan.',
            'promotion' => count($resolvedPromotions) === 1 ? $resolvedPromotions[0] : $resolvedPromotions,
            'discount_type' => $appliedDiscountType,
            'discount_value' => (float) ($appliedDiscountValue ?? 0),
            'discount_amount' => (float) $totalDiscountAmount,
            'shipping_discount' => (float) $totalShippingDiscount,
            'is_points_voucher' => $isPointsVoucher,
            'voucher_points' => $voucherPoints,
        ];
    }
}
