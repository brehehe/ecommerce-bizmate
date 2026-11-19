<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class CourierDispatchController extends Controller
{
    /**
     * Display the courier dispatch management dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = $request->input('tab', 'ready');

        $query = Transaction::with([
            'user:id,name,email,phone_number',
            'customerAddress',
            'items.product:id,name,image',
            'restoProfile:id,user_id,resto_name,address,latitude,longitude',
            'courierUser:id,name,phone_number,email',
            'paymentMethod:id,name,type',
        ])
            ->where(function ($q) {
                $q->where('shipping_courier', 'store_courier')
                    ->orWhere('fulfillment_type', 'store_courier');
            })
            ->latest();

        // Scope to current seller if not super admin
        if ($user->is_seller && ! $user->hasRole(['Super Admin', 'Admin'])) {
            $query->where('resto_id', $user->id);
        }

        if ($tab === 'ready') {
            // Freshly cooked or ready to be delivered by courier
            $query->whereIn('status', ['siap_diambil', 'dimasak', 'dikemas', 'diproses']);
        } elseif ($tab === 'delivering') {
            // Courier is on the road
            $query->whereIn('status', ['dikirim', 'sedang_dikirim']);
        } elseif ($tab === 'completed') {
            // Finished or completed deliveries
            $query->whereIn('status', ['selesai']);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        $transactions = $query->paginate(15)->withQueryString();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        // Stats counts
        $countsQuery = Transaction::where(function ($q) {
            $q->where('shipping_courier', 'store_courier')
                ->orWhere('fulfillment_type', 'store_courier');
        });

        if ($user->is_seller && ! $user->hasRole(['Super Admin', 'Admin'])) {
            $countsQuery->where('resto_id', $user->id);
        }

        $readyCount = (clone $countsQuery)->whereIn('status', ['siap_diambil', 'dimasak', 'dikemas', 'diproses'])->count();
        $deliveringCount = (clone $countsQuery)->whereIn('status', ['dikirim', 'sedang_dikirim'])->count();
        $completedCount = (clone $countsQuery)->where('status', 'selesai')->count();

        // Get active store couriers safely
        $hasCourierRole = Role::where('name', 'Kurir Toko')->where('guard_name', 'web')->exists();
        if ($hasCourierRole) {
            $courierQuery = User::role('Kurir Toko')->where('is_active', true);
            if ($user->is_seller && ! $user->hasRole(['Super Admin', 'Admin'])) {
                $courierQuery->where(function ($q) use ($user) {
                    $q->where('resto_id', $user->id)
                        ->orWhereNull('resto_id');
                });
            }
            $couriers = $courierQuery
                ->select('id', 'name', 'phone_number', 'email', 'resto_id')
                ->orderBy('name')
                ->get();
        } else {
            $couriers = collect();
        }

        return Inertia::render('Admin/CourierDispatch/Index', [
            'transactions' => $transactions,
            'couriers' => $couriers,
            'filters' => [
                'tab' => $tab,
                'search' => $request->input('search', ''),
            ],
            'counts' => [
                'ready' => $readyCount,
                'delivering' => $deliveringCount,
                'completed' => $completedCount,
                'couriers' => $couriers->count(),
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Update dispatch order status (e.g. courier departing or order completed).
     */
    public function updateStatus(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($request->isMethod('get')) {
            return redirect()->route('admin.courier-dispatch.index');
        }

        $validated = $request->validate([
            'status' => 'required|string|in:dikirim,selesai,siap_diambil',
            'notes' => 'nullable|string|max:255',
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $transaction->status;

        $transaction->status = $newStatus;

        if ($newStatus === 'selesai') {
            $transaction->delivery_arrived_at = now();

            // Auto-settle payment for COD orders when delivered
            $isCod = str_contains(strtolower($transaction->paymentMethod?->name ?? ''), 'cod')
                || str_contains(strtolower($transaction->paymentMethod?->name ?? ''), 'bayar di tempat');

            if ($isCod) {
                $transaction->payment_status = 'paid';
                if ($transaction->payment) {
                    $transaction->payment->update([
                        'status' => 'confirmed',
                        'confirmed_at' => now(),
                    ]);
                }
            }
        }

        $transaction->save();

        TransactionStatusHistory::create([
            'transaction_id' => $transaction->id,
            'status' => $newStatus,
            'description' => $validated['notes'] ?: "Status pengantaran kurir diubah dari {$oldStatus} ke {$newStatus}.",
            'created_by' => $request->user()->id,
        ]);

        $message = match ($newStatus) {
            'dikirim' => 'Kurir sedang dalam perjalanan mengantar pesanan ke alamat pelanggan.',
            'selesai' => 'Pesanan berhasil diserahkan ke pelanggan dan berstatus selesai!',
            default => "Status pesanan berhasil diperbarui ke {$newStatus}.",
        };

        $previousUrl = url()->previous();
        if ($previousUrl && ! str_contains($previousUrl, 'status')) {
            return redirect()->to($previousUrl)->with('success', $message);
        }

        return redirect()->route('admin.courier-dispatch.index')->with('success', $message);
    }

    /**
     * Store a newly created courier account for the resto / store.
     */
    public function storeCourier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone_number' => 'required|string|max:30',
            'password' => 'required|string|min:6',
        ]);

        $user = $request->user();
        $courier = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'password' => $validated['password'],
            'resto_id' => $user->is_seller ? $user->id : ($request->input('resto_id') ?: null),
            'is_active' => true,
        ]);

        if (! Role::where('name', 'Kurir Toko')->where('guard_name', 'web')->exists()) {
            Role::create(['name' => 'Kurir Toko', 'guard_name' => 'web']);
        }

        $courier->assignRole('Kurir Toko');

        return redirect()->route('admin.courier-dispatch.index')->with('success', "Akun kurir {$courier->name} berhasil didaftarkan! Kurir dapat login di portal /kurir/login menggunakan email: {$courier->email}.");
    }

    /**
     * Assign a specific courier to a delivery order.
     */
    public function assignCourier(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'courier_user_id' => 'required|exists:users,id',
        ]);

        $courier = User::findOrFail($validated['courier_user_id']);
        $transaction->update([
            'courier_user_id' => $courier->id,
            'courier_name' => $courier->name,
        ]);

        $transaction->statusHistories()->create([
            'status' => $transaction->status,
            'description' => "Kurir resto ditugaskan: {$courier->name} ({$courier->phone_number}).",
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.courier-dispatch.index')->with('success', "Pesanan #{$transaction->transaction_number} berhasil ditugaskan ke kurir {$courier->name}.");
    }
}
