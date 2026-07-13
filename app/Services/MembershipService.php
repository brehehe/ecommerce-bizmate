<?php

namespace App\Services;

use App\Models\CustomerMembership;
use App\Models\MembershipCashback;
use App\Models\MembershipHistory;
use App\Models\MembershipLevel;
use App\Models\MembershipPoint;
use App\Models\MembershipVoucher;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipService
{
    /**
     * Recalculate a customer's stats and check if level should change.
     */
    public function syncMembership(User $user): void
    {
        DB::transaction(function () use ($user) {
            $stats = $this->calculateStats($user);

            // Ensure user has a membership record
            $membership = CustomerMembership::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'membership_level_id' => $this->getDefaultLevel()?->id ?? $this->getLowestLevel()->id,
                    'joined_at' => now()->toDateString(),
                ]
            );

            // Update stats
            $membership->update([
                'total_purchase' => $stats['total_purchase'],
                'total_transactions' => $stats['total_transactions'],
                'total_products' => $stats['total_products'],
            ]);

            // Check if level should change
            $currentLevel = $membership->level;
            $eligibleLevel = $this->getEligibleLevel($stats);

            if ($eligibleLevel && $eligibleLevel->id !== $currentLevel?->id) {
                $action = ($eligibleLevel->order > ($currentLevel?->order ?? -1))
                    ? 'upgraded'
                    : 'downgraded';

                // Only downgrade if auto_downgrade is enabled for current level
                if ($action === 'downgraded' && ! $currentLevel?->auto_downgrade) {
                    return;
                }

                $this->changeMembershipLevel($user, $membership, $eligibleLevel, $action, 'Otomatis oleh sistem');
            }
        });
    }

    /**
     * Calculate user's purchase stats based on period type.
     *
     * @return array{total_purchase: float, total_transactions: int, total_products: int}
     */
    public function calculateStats(User $user, ?string $periodType = null): array
    {
        $query = Transaction::where('user_id', $user->id)
            ->where('status', 'selesai');

        if ($periodType && $periodType !== 'lifetime') {
            $months = match ($periodType) {
                '12_months' => 12,
                '6_months' => 6,
                '3_months' => 3,
                default => null,
            };

            if ($months) {
                $query->where('created_at', '>=', now()->subMonths($months));
            }
        }

        $transactions = $query->with('items')->get();

        return [
            'total_purchase' => (float) $transactions->sum('grand_total'),
            'total_transactions' => $transactions->count(),
            'total_products' => $transactions->sum(fn ($t) => $t->items->sum('quantity')),
        ];
    }

    /**
     * Find the highest level the user qualifies for.
     */
    public function getEligibleLevel(array $stats): ?MembershipLevel
    {
        return MembershipLevel::active()
            ->orderBy('order', 'desc')
            ->get()
            ->first(function (MembershipLevel $level) use ($stats) {
                return (float) $stats['total_purchase'] >= (float) $level->min_total_purchase
                    && (int) $stats['total_transactions'] >= (int) $level->min_total_transactions
                    && (int) $stats['total_products'] >= (int) $level->min_total_products;
            });
    }

    /**
     * Manually assign a membership level to a user.
     */
    public function assignLevel(User $user, MembershipLevel $level, string $reason = '', ?string $processedBy = null): CustomerMembership
    {
        return DB::transaction(function () use ($user, $level, $reason, $processedBy) {
            $membership = CustomerMembership::firstOrCreate(
                ['user_id' => $user->id],
                ['joined_at' => now()->toDateString()]
            );

            $currentLevel = $membership->level;
            $action = 'assigned';

            if ($currentLevel) {
                $action = $level->order > $currentLevel->order ? 'upgraded' : ($level->order < $currentLevel->order ? 'downgraded' : 'assigned');
            }

            $this->changeMembershipLevel($user, $membership, $level, $action, $reason, $processedBy);

            return $membership->fresh(['level']);
        });
    }

    /**
     * Change membership level and log history.
     */
    protected function changeMembershipLevel(
        User $user,
        CustomerMembership $membership,
        MembershipLevel $newLevel,
        string $action,
        string $reason = '',
        ?string $processedBy = null
    ): void {
        $fromLevelId = $membership->membership_level_id;
        $stats = $this->calculateStats($user);

        // Set expiry
        $expiresAt = null;
        if ($newLevel->validity_months) {
            $expiresAt = now()->addMonths($newLevel->validity_months)->toDateString();
        }

        $membership->update([
            'membership_level_id' => $newLevel->id,
            'expires_at' => $expiresAt,
        ]);

        MembershipHistory::create([
            'user_id' => $user->id,
            'from_level_id' => $fromLevelId,
            'to_level_id' => $newLevel->id,
            'action' => $action,
            'reason' => $reason,
            'total_purchase_at_time' => $stats['total_purchase'],
            'total_transactions_at_time' => $stats['total_transactions'],
            'processed_by' => $processedBy,
        ]);

        // Award upgrade benefits
        if ($action === 'upgraded') {
            $this->awardUpgradeBenefits($user, $newLevel);
        }
    }

    /**
     * Award benefits when user upgrades to a new level.
     */
    protected function awardUpgradeBenefits(User $user, MembershipLevel $level): void
    {
        foreach ($level->activeBenefits as $benefit) {
            if ($benefit->type === 'auto_voucher') {
                $this->issueVoucher($user, $level, 'level_upgrade');
            }
        }
    }

    /**
     * Issue a membership voucher to a user.
     */
    public function issueVoucher(User $user, MembershipLevel $level, string $trigger = 'auto_assign'): MembershipVoucher
    {
        $discountBenefit = $level->activeBenefits()
            ->whereIn('type', ['discount_percentage', 'discount_nominal'])
            ->first();

        $discountType = $discountBenefit?->type === 'discount_nominal' ? 'nominal' : 'percentage';
        $discountValue = $discountBenefit?->value ?? 10;

        return MembershipVoucher::create([
            'user_id' => $user->id,
            'membership_level_id' => $level->id,
            'code' => strtoupper('MBR-'.Str::random(8)),
            'label' => 'Voucher '.$level->name,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_purchase' => 0,
            'trigger' => $trigger,
            'status' => 'active',
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
        ]);
    }

    /**
     * Add points to a user after a completed transaction.
     */
    public function awardPoints(User $user, Transaction $transaction): void
    {
        $membership = $user->membership()->with('level.activeBenefits')->first();
        if (! $membership) {
            return;
        }

        $multiplier = 1.0;
        $pointMultiplierBenefit = $membership->level->activeBenefits
            ->where('type', 'point_multiplier')
            ->first();

        if ($pointMultiplierBenefit) {
            $multiplier = (float) $pointMultiplierBenefit->value;
        }

        $basePoints = (int) floor($transaction->grand_total / 1000); // 1 point per Rp1.000
        $totalPoints = (int) round($basePoints * $multiplier);

        if ($totalPoints > 0) {
            MembershipPoint::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'amount' => $totalPoints,
                'type' => 'earned',
                'description' => 'Poin dari transaksi '.$transaction->transaction_number,
                'expires_at' => now()->addYear()->toDateString(),
            ]);

            $membership->increment('total_points', $totalPoints);
        }
    }

    /**
     * Award cashback after a completed transaction.
     */
    public function awardCashback(User $user, Transaction $transaction): void
    {
        $membership = $user->membership()->with('level.activeBenefits')->first();
        if (! $membership) {
            return;
        }

        $cashbackBenefit = $membership->level->activeBenefits
            ->whereIn('type', ['cashback_percentage', 'cashback_nominal'])
            ->first();

        if (! $cashbackBenefit) {
            return;
        }

        $cashbackAmount = $cashbackBenefit->type === 'cashback_percentage'
            ? round($transaction->grand_total * ($cashbackBenefit->value / 100), 2)
            : (float) $cashbackBenefit->value;

        if ($cashbackAmount > 0) {
            MembershipCashback::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'membership_level_id' => $membership->membership_level_id,
                'amount' => $cashbackAmount,
                'status' => 'pending',
                'description' => 'Cashback dari transaksi '.$transaction->transaction_number,
            ]);

            $membership->increment('total_cashback', $cashbackAmount);
        }
    }

    /**
     * Get the next level above a given level.
     */
    public function getNextLevel(MembershipLevel $current): ?MembershipLevel
    {
        return MembershipLevel::active()
            ->where('order', '>', $current->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Calculate progress toward the next level.
     *
     * @return array{next_level: MembershipLevel|null, progress_purchase: float, progress_transactions: float, remaining_purchase: float, remaining_transactions: int}
     */
    public function getProgressToNextLevel(User $user): array
    {
        $membership = $user->membership()->with('level')->first();
        if (! $membership) {
            return ['next_level' => null];
        }

        $nextLevel = $this->getNextLevel($membership->level);
        if (! $nextLevel) {
            return ['next_level' => null];
        }

        $purchasePct = $nextLevel->min_total_purchase > 0
            ? min(100, ($membership->total_purchase / $nextLevel->min_total_purchase) * 100)
            : 100;

        $transactionPct = $nextLevel->min_total_transactions > 0
            ? min(100, ($membership->total_transactions / $nextLevel->min_total_transactions) * 100)
            : 100;

        return [
            'next_level' => $nextLevel,
            'progress_purchase' => round($purchasePct, 1),
            'progress_transactions' => round($transactionPct, 1),
            'remaining_purchase' => max(0, $nextLevel->min_total_purchase - $membership->total_purchase),
            'remaining_transactions' => max(0, $nextLevel->min_total_transactions - $membership->total_transactions),
        ];
    }

    /**
     * Get the lowest active membership level (for new members).
     */
    public function getLowestLevel(): MembershipLevel
    {
        return MembershipLevel::active()->ordered()->firstOrFail();
    }

    /**
     * Get the default (first/lowest) membership level.
     */
    public function getDefaultLevel(): ?MembershipLevel
    {
        return MembershipLevel::active()->ordered()->first();
    }

    /**
     * Get dashboard statistics for admin.
     *
     * @return array<string, mixed>
     */
    public function getDashboardStats(): array
    {
        $totalMembers = CustomerMembership::count();
        $newThisMonth = CustomerMembership::whereMonth('joined_at', now()->month)->count();
        $totalCashback = MembershipCashback::where('status', 'credited')->sum('amount');
        $totalVouchers = MembershipVoucher::count();
        $totalPoints = MembershipPoint::where('type', 'earned')->sum('amount');

        $byLevel = MembershipLevel::withCount('customerMemberships')
            ->active()
            ->ordered()
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->badge_color,
                'count' => $l->customer_memberships_count,
            ]);

        return [
            'total_members' => $totalMembers,
            'new_this_month' => $newThisMonth,
            'total_cashback' => $totalCashback,
            'total_vouchers' => $totalVouchers,
            'total_points' => $totalPoints,
            'by_level' => $byLevel,
        ];
    }

    /**
     * Get all membership benefits relevant at checkout.
     *
     * @return array{type: string, value: float, free_shipping: bool}
     */
    public function getMembershipCheckoutBenefits(?User $user): array
    {
        $defaults = ['type' => 'none', 'value' => 0.0, 'free_shipping' => false];

        if (! $user) {
            return $defaults;
        }

        $membership = $user->membership()->with('level.activeBenefits')->first();

        if (! $membership || ! $membership->level) {
            return $defaults;
        }

        $benefits = $membership->level->activeBenefits;

        $discountPct = $benefits->where('type', 'discount_percentage')->first();
        $discountNominal = $benefits->where('type', 'discount_nominal')->first();
        $freeShipping = $benefits->where('type', 'free_shipping')->first();

        $discountType = 'none';
        $discountValue = 0.0;

        if ($discountPct) {
            $discountType = 'percentage';
            $discountValue = (float) $discountPct->value;
        } elseif ($discountNominal) {
            $discountType = 'nominal';
            $discountValue = (float) $discountNominal->value;
        }

        return [
            'type' => $discountType,
            'value' => $discountValue,
            'free_shipping' => $freeShipping !== null,
        ];
    }

    /**
     * Get membership discount percentage for a given user.
     * Returns 0 if user has no membership or no discount benefit.
     *
     * @deprecated Use getMembershipCheckoutBenefits() for full checkout benefit resolution.
     */
    public function getMembershipDiscountForUser(?User $user): float
    {
        if (! $user) {
            return 0;
        }

        $membership = $user->membership()->with('level.activeBenefits')->first();

        if (! $membership || ! $membership->level) {
            return 0;
        }

        $discountBenefit = $membership->level->activeBenefits
            ->where('type', 'discount_percentage')
            ->first();

        return $discountBenefit ? (float) $discountBenefit->value : 0;
    }

    /**
     * Apply membership discount to a price.
     * Returns the discounted price.
     */
    public function applyMembershipDiscount(float $price, float $discountPct): float
    {
        if ($discountPct <= 0) {
            return $price;
        }

        return round($price * (1 - $discountPct / 100), 2);
    }

    /**
     * Get membership info for a user to share with frontend.
     *
     * @return array{level: array|null, discount_percentage: float, benefits: array}
     */
    public function getMembershipInfoForFrontend(?User $user): array
    {
        if (! $user) {
            return [
                'level' => null,
                'discount_percentage' => 0,
                'benefits' => [],
                'next_level' => null,
                'progress' => null,
            ];
        }

        $membership = $user->membership()->with('level.activeBenefits')->first();

        if (! $membership || ! $membership->level) {
            return [
                'level' => null,
                'discount_percentage' => 0,
                'benefits' => [],
                'next_level' => null,
                'progress' => null,
            ];
        }

        $level = $membership->level;
        $discountBenefit = $level->activeBenefits->where('type', 'discount_percentage')->first();
        $progress = $this->getProgressToNextLevel($user);

        return [
            'level' => [
                'id' => $level->id,
                'name' => $level->name,
                'icon' => $level->icon,
                'badge_color' => $level->badge_color,
            ],
            'discount_percentage' => $discountBenefit ? (float) $discountBenefit->value : 0,
            'benefits' => $level->activeBenefits->map(fn ($b) => [
                'type' => $b->type,
                'label' => $b->label,
                'value' => $b->value,
                'icon' => $b->icon,
            ])->values()->all(),
            'next_level' => $progress['next_level'] ? [
                'id' => $progress['next_level']->id,
                'name' => $progress['next_level']->name,
                'badge_color' => $progress['next_level']->badge_color,
            ] : null,
            'progress' => isset($progress['progress_purchase']) ? [
                'purchase' => $progress['progress_purchase'],
                'transactions' => $progress['progress_transactions'],
                'remaining_purchase' => $progress['remaining_purchase'],
                'remaining_transactions' => $progress['remaining_transactions'],
            ] : null,
        ];
    }

    /**
     * Get the current user's membership level order (for feature gating).
     * Returns -1 if user has no membership.
     */
    public function getUserLevelOrder(?User $user): int
    {
        if (! $user) {
            return -1;
        }

        $membership = $user->membership()->with('level')->first();

        return $membership?->level?->order ?? -1;
    }

    /**
     * Get flash sale early access minutes for a user.
     */
    public function getFlashSaleEarlyAccessMinutes(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $membership = $user->membership()->with('level.activeBenefits')->first();

        if (! $membership?->level) {
            return 0;
        }

        $benefit = $membership->level->activeBenefits
            ->where('type', 'flash_sale_access')
            ->first();

        return $benefit ? (int) $benefit->value : 0;
    }

    /**
     * Determine if a user can access a flash sale promotion
     * (supports early access based on member_early_access_minutes).
     */
    public function canAccessFlashSale(?User $user, Promotion $promotion): bool
    {
        $now = now();

        // Always accessible if within normal time window
        if ($promotion->start_time <= $now && $promotion->end_time >= $now) {
            return true;
        }

        // Check early access for members
        $earlyMinutes = $this->getFlashSaleEarlyAccessMinutes($user);

        if ($earlyMinutes > 0 && $promotion->member_early_access_minutes > 0) {
            $memberEarlyMinutes = min($earlyMinutes, (int) $promotion->member_early_access_minutes);
            $memberStartTime = $promotion->start_time->copy()->subMinutes($memberEarlyMinutes);

            return $memberStartTime <= $now && $promotion->end_time >= $now;
        }

        return false;
    }

    /**
     * Check if user can view an exclusive product.
     */
    public function canViewExclusiveProduct(?User $user, Product $product): bool
    {
        if (! $product->is_exclusive) {
            return true;
        }

        return $this->getUserLevelOrder($user) >= (int) ($product->exclusive_min_level_order ?? 0);
    }

    /**
     * Check if user can view an early-access product.
     */
    public function canViewEarlyAccessProduct(?User $user, Product $product): bool
    {
        if (! $product->is_early_access) {
            return true;
        }

        // After early_access_until, everyone can see
        if ($product->early_access_until && $product->early_access_until <= now()) {
            return true;
        }

        return $this->getUserLevelOrder($user) >= (int) ($product->early_access_min_level_order ?? 0);
    }

    /**
     * Check if user has priority_cs benefit.
     */
    public function hasPriorityCustomerService(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $membership = $user->membership()->with('level.activeBenefits')->first();

        if (! $membership?->level) {
            return false;
        }

        return $membership->level->activeBenefits
            ->where('type', 'priority_cs')
            ->isNotEmpty();
    }
}
