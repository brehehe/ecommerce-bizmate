<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestoProfile;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RestoApprovalController extends Controller
{
    /**
     * Authorize that only super admin or admin can access resto approvals.
     */
    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Akses terbatas untuk Administrator Platform.');
        }
    }

    /**
     * Display list of resto partners and applications.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $tab = $request->input('tab', 'all');
        $search = $request->input('search', '');

        $query = RestoProfile::with([
            'user:id,name,email,phone_number,store_name,store_slug',
        ])
            ->withCount(['products', 'transactions'])
            ->latest();

        if ($tab === 'pending') {
            $query->where('approval_status', 'pending_approval');
        } elseif ($tab === 'approved') {
            $query->where('approval_status', 'approved');
        } elseif ($tab === 'rejected') {
            $query->where('approval_status', 'rejected');
        } elseif ($tab === 'suspended') {
            $query->where('approval_status', 'suspended');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('resto_name', 'like', "%{$search}%")
                    ->orWhere('culinary_category', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        $restos = $query->paginate(15)->withQueryString();

        // Calculate count badges
        $counts = [
            'all' => RestoProfile::count(),
            'pending' => RestoProfile::where('approval_status', 'pending_approval')->count(),
            'approved' => RestoProfile::where('approval_status', 'approved')->count(),
            'rejected' => RestoProfile::where('approval_status', 'rejected')->count(),
            'suspended' => RestoProfile::where('approval_status', 'suspended')->count(),
        ];

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Restos/Index', [
            'restos' => $restos,
            'counts' => $counts,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Approve a restaurant partner.
     */
    public function approve(Request $request, RestoProfile $restoProfile): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $restoProfile->approval_status = 'approved';
        $restoProfile->is_open_now = true;
        $restoProfile->save();

        // Ensure user has Seller role and flag
        $restoUser = $restoProfile->user;
        if ($restoUser) {
            $restoUser->is_seller = true;
            if (! Role::where('name', 'Seller')->exists()) {
                Role::create(['name' => 'Seller']);
            }
            if (! $restoUser->hasRole('Seller')) {
                $restoUser->assignRole('Seller');
            }
            $restoUser->save();
        }

        return back()->with('success', "Resto '{$restoProfile->resto_name}' berhasil disetujui dan aktif di platform!");
    }

    /**
     * Reject a restaurant application.
     */
    public function reject(Request $request, RestoProfile $restoProfile): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $restoProfile->approval_status = 'rejected';
        $restoProfile->is_open_now = false;
        $restoProfile->save();

        return back()->with('success', "Pendaftaran resto '{$restoProfile->resto_name}' ditolak.");
    }

    /**
     * Suspend an active restaurant partner.
     */
    public function suspend(Request $request, RestoProfile $restoProfile): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $restoProfile->approval_status = 'suspended';
        $restoProfile->is_open_now = false;
        $restoProfile->save();

        return back()->with('success', "Resto '{$restoProfile->resto_name}' ditangguhkan sementara.");
    }

    /**
     * Admin toggle open/close status on behalf of restaurant.
     */
    public function toggleOpen(Request $request, RestoProfile $restoProfile): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $restoProfile->is_open_now = ! $restoProfile->is_open_now;
        $restoProfile->save();

        $msg = $restoProfile->is_open_now
            ? "Status resto '{$restoProfile->resto_name}' sekarang BUKA."
            : "Status resto '{$restoProfile->resto_name}' sekarang TUTUP SEMENTARA.";

        return back()->with('success', $msg);
    }
}
