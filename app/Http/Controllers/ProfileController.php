<?php

namespace App\Http\Controllers;

use App\Models\CoinHistory;
use App\Models\CustomerBankAccount;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'phone_number' => $request->user()->phone_number,
                'gender' => $request->user()->gender,
                'birth_date' => $request->user()->birth_date,
                'avatar' => $request->user()->avatar,
                'coins_balance' => $request->user()->coins_balance,
            ],
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
            'phone_number' => 'nullable|string|max:20',
            'gender' => 'nullable|in:Laki-laki,Perempuan',
            'birth_date' => 'nullable|date',
            'avatar' => 'nullable|image|max:2048', // max 2MB
            'current_password' => 'required|current_password',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh pengguna lain.',
            'avatar.image' => 'File harus berupa gambar.',
            'avatar.max' => 'Ukuran gambar maksimal 2MB.',
            'current_password.required' => 'Kata sandi saat ini wajib diisi untuk menyimpan perubahan.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->phone_number = $request->input('phone_number');
        $user->gender = $request->input('gender');
        $user->birth_date = $request->input('birth_date');

        $user->save();

        return redirect()->back()->with('success', 'Profil Anda berhasil diperbarui!');
    }

    /**
     * Update the customer's password.
     */
    public function updateCustomerPassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user->password = Hash::make($request->input('password'));
        $user->save();

        return redirect()->back()->with('success', 'Kata sandi berhasil diperbarui!');
    }

    /**
     * Show the admin's profile edit page.
     */
    public function showAdminProfile(Request $request): Response
    {
        return Inertia::render('Admin/Profile', [
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'phone_number' => $request->user()->phone_number,
                'gender' => $request->user()->gender,
                'birth_date' => $request->user()->birth_date,
                'avatar' => $request->user()->avatar,
                'coins_balance' => $request->user()->coins_balance,
            ],
        ]);
    }

    /**
     * Update the admin's profile information and avatar.
     */
    public function updateAdminProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'phone_number' => 'nullable|string|max:20',
            'gender' => 'nullable|in:Laki-laki,Perempuan',
            'birth_date' => 'nullable|date',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'current_password' => 'required|current_password',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh pengguna lain.',
            'current_password.required' => 'Kata sandi wajib diisi untuk memperbarui profil.',
            'current_password.current_password' => 'Kata sandi yang Anda masukkan tidak cocok.',
            'avatar.image' => 'File harus berupa gambar.',
            'avatar.max' => 'Ukuran gambar maksimal 2MB.',
        ]);

        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->phone_number = $request->input('phone_number');
        $user->gender = $request->input('gender');
        $user->birth_date = $request->input('birth_date');

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return redirect()->back()->with('success', 'Profil admin berhasil diperbarui!');
    }

    /**
     * Update the admin's password.
     */
    public function updateAdminPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $request->user()->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return redirect()->back()->with('success', 'Kata sandi admin berhasil diperbarui!');
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
                $q->where('description', 'ilike', '%'.$search.'%')
                    ->orWhereHas('transaction', function ($t) use ($search) {
                        $t->where('transaction_number', 'ilike', '%'.$search.'%');
                    });
            });
        }

        $history = $query->paginate(10);

        return response()->json($history);
    }
}
