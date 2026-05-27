<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class MasterDataController extends Controller
{
    /**
     * Display a listing of admin users.
     */
    public function admins(Request $request)
    {
        $query = User::with('roles')->whereHas('roles', function ($q) use ($request) {
            $q->where('name', '!=', 'Customer');
            if ($request->filled('role') && $request->role !== 'Semua') {
                $q->where('name', $request->role);
            }
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 10);
        $admins = $query->paginate($perPage)->withQueryString();

        // Roles for the dropdown when adding an admin (excluding Customer)
        $roles = Role::where('name', '!=', 'Customer')->get();

        return Inertia::render('Admin/MasterData/Admins', [
            'users' => $admins,
            'roles' => $roles,
            'filters' => [
                'search' => $request->search,
                'role' => $request->role,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created admin.
     */
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', 'Admin berhasil ditambahkan.');
    }

    /**
     * Update the specified admin.
     */
    public function updateAdmin(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->update([
            'name' => $validated['name'],
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => bcrypt($validated['password'])]);
        }

        // Sync role (we assume admin has only 1 primary role for this UI)
        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'Data admin berhasil diperbarui.');
    }

    /**
     * Remove the specified admin.
     */
    public function destroyAdmin(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            return back()->with('error', 'Super Admin tidak dapat dihapus.');
        }

        if (auth()->id() === $user->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return back()->with('success', 'Admin berhasil dihapus.');
    }

    /**
     * Toggle active status.
     */
    public function toggleActiveAdmin(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            return back()->with('error', 'Status Super Admin tidak dapat diubah.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Status admin berhasil diubah.');
    }

    /**
     * Display a listing of customers.
     */
    public function customers(Request $request)
    {
        // Customers are those who have the 'Customer' role
        $query = User::with('roles')->whereHas('roles', function ($q) {
            $q->where('name', 'Customer');
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 10);
        $customers = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/MasterData/Customers', [
            'users' => $customers,
            'filters' => [
                'search' => $request->search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function storeCustomer(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole('Customer');

        return back()->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    /**
     * Update the specified customer.
     */
    public function updateCustomer(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        $user->update([
            'name' => $validated['name'],
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => bcrypt($validated['password'])]);
        }

        return back()->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    /**
     * Remove the specified customer.
     */
    public function destroyCustomer(User $user)
    {
        $user->delete();

        return back()->with('success', 'Pelanggan berhasil dihapus.');
    }

    /**
     * Toggle active status.
     */
    public function toggleActiveCustomer(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Status pelanggan berhasil diubah.');
    }

    /**
     * Display a listing of the roles.
     */
    public function roles(Request $request)
    {
        $query = Role::withCount('users');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%");
        }

        $roles = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/MasterData/Roles', [
            'roles' => $roles,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Display a listing of payment methods.
     */
    public function paymentMethods(Request $request)
    {
        $query = PaymentMethod::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%");
        }

        $perPage = $request->get('perPage', 10);
        $paymentMethods = $query->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/MasterData/PaymentMethods', [
            'paymentMethods' => $paymentMethods,
            'filters' => [
                'search' => $request->search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created payment method.
     */
    public function storePaymentMethod(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:manual,gateway',
            'bank_name' => 'nullable|string|max:255|required_if:type,manual',
            'account_number' => 'nullable|string|max:255|required_if:type,manual',
            'account_name' => 'nullable|string|max:255|required_if:type,manual',
            'api_key' => 'nullable|string|max:255|required_if:type,gateway',
            'api_secret' => 'nullable|string|max:255',
            'admin_fee' => 'nullable|numeric|min:0',
        ]);

        PaymentMethod::create(array_merge($validated, ['is_active' => true]));

        return back()->with('success', 'Metode Pembayaran berhasil ditambahkan.');
    }

    /**
     * Update the specified payment method.
     */
    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:manual,gateway',
            'bank_name' => 'nullable|string|max:255|required_if:type,manual',
            'account_number' => 'nullable|string|max:255|required_if:type,manual',
            'account_name' => 'nullable|string|max:255|required_if:type,manual',
            'api_key' => 'nullable|string|max:255|required_if:type,gateway',
            'api_secret' => 'nullable|string|max:255',
            'admin_fee' => 'nullable|numeric|min:0',
        ]);

        // If type is switched, nullify the irrelevant fields
        if ($validated['type'] === 'manual') {
            $validated['api_key'] = null;
            $validated['api_secret'] = null;
        } else {
            $validated['bank_name'] = null;
            $validated['account_number'] = null;
            $validated['account_name'] = null;
        }

        $paymentMethod->update($validated);

        return back()->with('success', 'Metode Pembayaran berhasil diperbarui.');
    }

    /**
     * Remove the specified payment method.
     */
    public function destroyPaymentMethod(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        return back()->with('success', 'Metode Pembayaran berhasil dihapus.');
    }

    /**
     * Toggle active status of payment method.
     */
    public function toggleActivePaymentMethod(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        return back()->with('success', 'Status metode pembayaran berhasil diubah.');
    }
}
