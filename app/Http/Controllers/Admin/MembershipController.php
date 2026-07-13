<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerMembership;
use App\Models\MembershipBenefit;
use App\Models\MembershipCashback;
use App\Models\MembershipHistory;
use App\Models\MembershipLevel;
use App\Models\MembershipPoint;
use App\Models\MembershipVoucher;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function __construct(protected MembershipService $membershipService) {}

    // ── Dashboard ────────────────────────────────────────────

    public function dashboard(): Response
    {
        $stats = $this->membershipService->getDashboardStats();

        $growthData = CustomerMembership::selectRaw("to_char(joined_at, 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get();

        return Inertia::render('Admin/Membership/Dashboard', [
            'stats' => $stats,
            'growthData' => $growthData,
        ]);
    }

    // ── Levels ───────────────────────────────────────────────

    public function levels(Request $request): Response
    {
        $levels = MembershipLevel::withCount('customerMemberships')
            ->with('activeBenefits')
            ->withTrashed()
            ->ordered()
            ->get();

        return Inertia::render('Admin/Membership/Levels', [
            'levels' => $levels,
        ]);
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'badge_color' => 'required|string|max:20',
            'description' => 'nullable|string',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'min_total_purchase' => 'required|numeric|min:0',
            'min_total_transactions' => 'required|integer|min:0',
            'min_total_products' => 'required|integer|min:0',
            'period_type' => 'required|in:lifetime,12_months,6_months,3_months',
            'auto_upgrade' => 'boolean',
            'auto_downgrade' => 'boolean',
            'validity_months' => 'nullable|integer|min:1',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        MembershipLevel::create($validated);

        return back()->with('flash', [
            'id' => uniqid(),
            'success' => 'Level membership berhasil dibuat.',
        ]);
    }

    public function updateLevel(Request $request, MembershipLevel $level): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'badge_color' => 'required|string|max:20',
            'description' => 'nullable|string',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'min_total_purchase' => 'required|numeric|min:0',
            'min_total_transactions' => 'required|integer|min:0',
            'min_total_products' => 'required|integer|min:0',
            'period_type' => 'required|in:lifetime,12_months,6_months,3_months',
            'auto_upgrade' => 'boolean',
            'auto_downgrade' => 'boolean',
            'validity_months' => 'nullable|integer|min:1',
        ]);

        $level->update($validated);

        return back()->with('flash', [
            'id' => uniqid(),
            'success' => 'Level membership berhasil diperbarui.',
        ]);
    }

    public function destroyLevel(MembershipLevel $level): RedirectResponse
    {
        if ($level->customerMemberships()->count() > 0) {
            return back()->with('flash', [
                'id' => uniqid(),
                'error' => 'Level tidak dapat dihapus karena masih memiliki member.',
            ]);
        }

        $level->delete();

        return back()->with('flash', [
            'id' => uniqid(),
            'success' => 'Level membership berhasil dihapus.',
        ]);
    }

    // ── Benefits ─────────────────────────────────────────────

    public function storeBenefit(Request $request, MembershipLevel $level): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'value' => 'required|numeric|min:0',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $level->benefits()->create($validated);

        return back()->with('flash', [
            'id' => uniqid(),
            'success' => 'Benefit berhasil ditambahkan.',
        ]);
    }

    public function updateBenefit(Request $request, MembershipBenefit $benefit): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'value' => 'required|numeric|min:0',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $benefit->update($validated);

        return back()->with('flash', [
            'id' => uniqid(),
            'success' => 'Benefit berhasil diperbarui.',
        ]);
    }

    public function destroyBenefit(MembershipBenefit $benefit): RedirectResponse
    {
        $benefit->delete();

        return back()->with('flash', [
            'id' => uniqid(),
            'success' => 'Benefit berhasil dihapus.',
        ]);
    }

    // ── Member detail ─────────────────────────────────────────

    public function showMember(User $user): Response
    {
        $membership = CustomerMembership::with(['level.activeBenefits'])
            ->where('user_id', $user->id)
            ->first();

        $histories = MembershipHistory::with(['fromLevel', 'toLevel', 'processedBy'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get();

        $vouchers = MembershipVoucher::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        $points = MembershipPoint::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        $cashbacks = MembershipCashback::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        $levels = MembershipLevel::active()->ordered()->get(['id', 'name', 'badge_color', 'order']);
        $progress = $membership ? $this->membershipService->getProgressToNextLevel($user) : null;

        return Inertia::render('Admin/Membership/Show', [
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'phone' => $user->phone_number,
                'birth_date' => $user->birth_date,
                'created_at' => $user->created_at?->toDateString(),
            ],
            'membership' => $membership,
            'histories' => $histories,
            'vouchers' => $vouchers,
            'points' => $points,
            'cashbacks' => $cashbacks,
            'levels' => $levels,
            'progress' => $progress,
        ]);
    }

    // ── Members list ─────────────────────────────────────────

    public function members(Request $request): Response
    {
        $query = CustomerMembership::with(['user', 'level'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->whereHas('user', function ($uq) use ($request) {
                    $search = $request->search;
                    $uq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('level'), function ($q) use ($request) {
                $q->where('membership_level_id', $request->level);
            });

        $members = $query->latest()->paginate(20)->withQueryString();
        $levels = MembershipLevel::active()->ordered()->get(['id', 'name', 'badge_color']);

        return Inertia::render('Admin/Membership/Members', [
            'members' => $members,
            'levels' => $levels,
            'filters' => [
                'search' => $request->search,
                'level' => $request->level,
            ],
        ]);
    }

    public function assignLevel(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'membership_level_id' => 'required|exists:membership_levels,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $level = MembershipLevel::findOrFail($validated['membership_level_id']);
        $this->membershipService->assignLevel(
            $user,
            $level,
            $validated['reason'] ?? 'Ditetapkan oleh admin',
            auth()->id()
        );

        return back()->with('flash', [
            'id' => uniqid(),
            'success' => "Level membership {$user->name} berhasil diubah menjadi {$level->name}.",
        ]);
    }

    // ── Histories ────────────────────────────────────────────

    public function histories(Request $request): Response
    {
        $query = MembershipHistory::with(['user', 'fromLevel', 'toLevel', 'processedBy'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->whereHas('user', function ($uq) use ($request) {
                    $uq->where('name', 'ilike', "%{$request->search}%")
                        ->orWhere('email', 'ilike', "%{$request->search}%");
                });
            })
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('level'), fn ($q) => $q->where('to_level_id', $request->level));

        $histories = $query->latest()->paginate(20)->withQueryString();
        $levels = MembershipLevel::active()->ordered()->get(['id', 'name']);

        return Inertia::render('Admin/Membership/Histories', [
            'histories' => $histories,
            'levels' => $levels,
            'filters' => [
                'search' => $request->search,
                'action' => $request->action,
                'level' => $request->level,
            ],
        ]);
    }

    // ── Sync membership for a specific user ──────────────────

    public function syncUser(User $user): RedirectResponse
    {
        $this->membershipService->syncMembership($user);

        return back()->with('flash', [
            'id' => uniqid(),
            'success' => "Membership {$user->name} berhasil disinkronkan.",
        ]);
    }
}
