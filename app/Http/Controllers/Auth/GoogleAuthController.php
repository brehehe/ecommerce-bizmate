<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect user to Google for OAuth authentication.
     */
    public function redirect(): RedirectResponse
    {
        if (empty(config('services.google.client_id')) || empty(config('services.google.client_secret'))) {
            return redirect()->route('login')->withErrors([
                'email' => 'Fitur Masuk dengan Google belum dikonfigurasi di server.',
            ]);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle OAuth callback from Google.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $errorDesc = $request->input('error_description') ?: 'Otorisasi masuk dengan Google dibatalkan.';

            return redirect()->route('login')->withErrors(['email' => $errorDesc]);
        }

        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();

            $user = $this->findOrCreateUser($googleUser);

            // Authenticate user
            Auth::login($user, true);
            $request->session()->regenerate();

            // Clear any lingering intended URL so customer is not accidentally sent to /admin
            session()->forget('url.intended');

            // If user has no roles and is not admin, ensure Customer role
            if (! $user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Admin Penjualan']) && ! $user->hasRole('Customer')) {
                $user->assignRole('Customer');
            }

            if ($user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Admin Penjualan'])) {
                return redirect('/admin')->with('success', 'Berhasil masuk dengan Google.');
            }

            return redirect('/')->with('success', 'Selamat datang! Berhasil masuk dengan akun Google.');

        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors([
                'email' => 'Gagal masuk dengan Google: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Link current logged-in user to Google account.
     */
    public function link(): RedirectResponse
    {
        if (empty(config('services.google.client_id')) || empty(config('services.google.client_secret'))) {
            return redirect()->back()->with('error', 'Integrasi akun Google belum dikonfigurasi.');
        }

        session(['google_link_user_id' => Auth::id()]);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle callback when linking Google account from profile.
     */
    public function linkCallback(Request $request): RedirectResponse
    {
        $userId = session('google_link_user_id');
        session()->forget('google_link_user_id');

        if (! $userId || $userId !== Auth::id()) {
            return redirect()->route('profile.edit')->withErrors(['google' => 'Sesi penautan akun tidak valid.']);
        }

        if ($request->has('error')) {
            return redirect()->route('profile.edit')->withErrors([
                'google' => 'Penautan akun Google dibatalkan.',
            ]);
        }

        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();

            // Check if this Google account is already linked to another user
            $existing = User::where('google_id', $googleUser->getId())->first();
            if ($existing && $existing->id !== $userId) {
                return redirect()->route('profile.edit')->withErrors([
                    'google' => 'Akun Google ini sudah terhubung dengan akun pengguna lain.',
                ]);
            }

            $user = User::findOrFail($userId);
            $user->update([
                'google_id' => $googleUser->getId(),
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken ?? $user->google_refresh_token,
            ]);

            return redirect()->route('profile.edit')->with('success', 'Akun Google berhasil ditautkan.');

        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('profile.edit')->withErrors([
                'google' => 'Gagal menautkan akun Google: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Unlink Google account from profile.
     */
    public function unlink(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasLocalPassword() && ! $user->isLinkedToPadelgigs()) {
            return redirect()->route('profile.edit')->withErrors([
                'google' => 'Silakan atur kata sandi terlebih dahulu sebelum memutuskan tautan Google.',
            ]);
        }

        $user->update([
            'google_id' => null,
            'google_token' => null,
            'google_refresh_token' => null,
        ]);

        return redirect()->route('profile.edit')->with('success', 'Tautan akun Google berhasil dilepas.');
    }

    /**
     * Unified user resolution strategy for Google OAuth:
     * 1. Check by google_id
     * 2. Check by email
     * 3. If exists, link Google credentials and preserve existing user data
     * 4. If not, create new User with Customer role
     */
    protected function findOrCreateUser($googleUser): User
    {
        $googleId = (string) $googleUser->getId();
        $email = trim($googleUser->getEmail() ?? '');
        $name = trim($googleUser->getName() ?? 'Google User');
        $avatar = $googleUser->getAvatar();
        $token = $googleUser->token;
        $refreshToken = $googleUser->refreshToken;

        $isSellerMode = (bool) config('app.is_seller', false);

        // 1. Check by Google ID
        if ($googleId !== '') {
            $user = User::where('google_id', $googleId)->first();
            if ($user) {
                $updates = [
                    'google_token' => $token,
                    'google_refresh_token' => $refreshToken ?? $user->google_refresh_token,
                    'last_active_at' => now(),
                ];

                $user->update($updates);

                if ($user->roles()->count() === 0) {
                    $user->assignRole('Customer');
                }

                return $user;
            }
        }

        // 2. Check by Email (Merge with existing store / customer)
        if ($email !== '') {
            $user = User::where('email', $email)->first();
            if ($user) {
                $updates = [
                    'google_id' => $googleId ?: null,
                    'google_token' => $token,
                    'google_refresh_token' => $refreshToken ?? $user->google_refresh_token,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'avatar' => $user->avatar ?: $avatar,
                    'last_active_at' => now(),
                ];

                $user->update($updates);

                if ($user->roles()->count() === 0) {
                    $user->assignRole('Customer');
                }

                return $user;
            }
        }

        // 3. Create completely new User as standard Customer
        $user = User::create([
            'google_id' => $googleId ?: null,
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
            'password' => null,
            'is_active' => true,
            'is_seller' => false,
            'store_name' => null,
            'store_slug' => null,
            'email_verified_at' => now(),
            'google_token' => $token,
            'google_refresh_token' => $refreshToken,
            'last_active_at' => now(),
        ]);

        $user->assignRole('Customer');

        return $user;
    }
}
