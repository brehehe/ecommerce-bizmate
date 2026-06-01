<?php

namespace App\Http\Controllers;

use App\Models\CoinHistory;
use App\Models\CustomerBankAccount;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the customer's profile edit page.
     */
    public function showCustomerProfile(Request $request): Response
    {
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Profile', [
            'user' => $request->user(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Update the customer's profile.
     */
    public function updateCustomerProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh pengguna lain.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user->name = $request->input('name');
        $user->email = $request->input('email');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->save();

        return redirect()->back()->with('success', 'Profil Anda berhasil diperbarui!');
    }

    /**
     * Show the admin's profile edit page.
     */
    public function showAdminProfile(Request $request): Response
    {
        return Inertia::render('Admin/Profile', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the admin's profile.
     */
    public function updateAdminProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh pengguna lain.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user->name = $request->input('name');
        $user->email = $request->input('email');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->save();

        return redirect()->back()->with('success', 'Profil admin berhasil diperbarui!');
    }

    /**
     * Show customer bank accounts page.
     */
    public function bankAccounts(Request $request): Response
    {
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/BankAccounts', [
            'bankAccounts' => $request->user()->customerBankAccounts()->orderByDesc('is_primary')->orderBy('id')->get(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Store a new bank account.
     */
    public function storeBankAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:150',
            'is_primary' => 'boolean',
        ], [
            'bank_name.required' => 'Nama bank wajib diisi.',
            'account_number.required' => 'Nomor rekening wajib diisi.',
            'account_name.required' => 'Nama pemilik rekening wajib diisi.',
        ]);

        // If this is set as primary, unset all others
        if ($request->boolean('is_primary')) {
            $user->customerBankAccounts()->update(['is_primary' => false]);
        }

        // If this is the first account, make it primary
        $isPrimary = $request->boolean('is_primary') || $user->customerBankAccounts()->count() === 0;

        $user->customerBankAccounts()->create([
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'is_primary' => $isPrimary,
        ]);

        return back()->with('success', 'Rekening bank berhasil ditambahkan.');
    }

    /**
     * Update a bank account.
     */
    public function updateBankAccount(Request $request, CustomerBankAccount $bankAccount): RedirectResponse
    {
        $user = $request->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:150',
            'is_primary' => 'boolean',
        ]);

        if ($request->boolean('is_primary')) {
            $user->customerBankAccounts()->update(['is_primary' => false]);
        }

        $bankAccount->update([
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('success', 'Rekening bank berhasil diperbarui.');
    }

    /**
     * Delete a bank account.
     */
    public function destroyBankAccount(Request $request, CustomerBankAccount $bankAccount): RedirectResponse
    {
        $user = $request->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $wasPrimary = $bankAccount->is_primary;
        $bankAccount->delete();

        // If deleted account was primary, set the first remaining as primary
        if ($wasPrimary) {
            $user->customerBankAccounts()->orderBy('id')->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Rekening bank berhasil dihapus.');
    }

    /**
     * Set a bank account as primary.
     */
    public function makePrimaryBankAccount(Request $request, CustomerBankAccount $bankAccount): RedirectResponse
    {
        $user = $request->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $user->customerBankAccounts()->update(['is_primary' => false]);
        $bankAccount->update(['is_primary' => true]);

        return back()->with('success', 'Rekening utama berhasil diubah.');
    }

    /**
     * Get customer coin transaction history.
     */
    public function coinHistory(Request $request)
    {
        $user = $request->user();
        $query = CoinHistory::where('user_id', $user->id)->latest();

        if ($request->filled('type')) {
            $type = $request->input('type');
            if ($type === 'masuk') {
                $query->where('amount', '>', 0);
            } elseif ($type === 'keluar') {
                $query->where('amount', '<', 0);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%'.$search.'%')
                    ->orWhereHas('transaction', function ($t) use ($search) {
                        $t->where('transaction_number', 'like', '%'.$search.'%');
                    });
            });
        }

        $history = $query->paginate(10);

        return response()->json($history);
    }
}
