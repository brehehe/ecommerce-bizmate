<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PadelGigsOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PadelGigsAuthController extends Controller
{
    public function __construct(
        protected PadelGigsOAuthService $oauthService
    ) {}

    /**
     * Redirect user to PadelGigs for OAuth authorization.
     */
    public function redirect(): RedirectResponse
    {
        if (! config('services.padelgigs.enabled', true)) {
            return redirect()->route('login')->withErrors(['email' => 'Fitur Masuk dengan PadelGigs sedang dinonaktifkan.']);
        }

        $auth = $this->oauthService->getAuthorizationUrl();

        return redirect($auth['url']);
    }

    /**
     * Handle OAuth callback from PadelGigs.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! config('services.padelgigs.enabled', true)) {
            return redirect()->route('login')->withErrors(['email' => 'Fitur Masuk dengan PadelGigs sedang dinonaktifkan.']);
        }

        if ($request->has('error')) {
            $errorDesc = $request->input('error_description') ?: 'Otorisasi login PadelGigs dibatalkan.';

            return redirect()->route('login')->withErrors(['email' => $errorDesc]);
        }

        $code = $request->input('code');
        $state = $request->input('state');

        if (! $code || ! $state) {
            return redirect()->route('login')->withErrors(['email' => 'Parameter autentikasi SSO PadelGigs tidak lengkap.']);
        }

        try {
            // 1. Exchange code for OAuth tokens
            $tokens = $this->oauthService->exchangeCodeForTokens($code, $state);

            // 2. Fetch user profile from PadelGigs
            $userInfo = $this->oauthService->getUserInfo($tokens['access_token']);

            // 3. Find or unify account
            $user = $this->findOrCreateUser($userInfo, $tokens);

            // 4. Authenticate user
            Auth::login($user, true);
            $request->session()->regenerate();

            // Clear any lingering intended URL so customer/seller is not sent to /admin
            session()->forget('url.intended');

            // If user has no roles and is not admin, ensure Customer role
            if (! $user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Admin Penjualan']) && ! $user->hasRole('Customer')) {
                $user->assignRole('Customer');
            }

            if ($user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Admin Penjualan'])) {
                return redirect('/admin')->with('success', 'Berhasil masuk dengan PadelGigs.');
            }

            return redirect('/')->with('success', 'Selamat datang! Berhasil masuk dengan akun PadelGigs.');

        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors([
                'email' => 'Gagal masuk dengan PadelGigs: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Link current logged-in user to PadelGigs account.
     */
    public function link(): RedirectResponse
    {
        if (! config('services.padelgigs.enabled', true)) {
            return redirect()->back()->with('error', 'Integrasi akun PadelGigs sedang dinonaktifkan.');
        }

        session(['padelgigs_link_user_id' => Auth::id()]);

        $auth = $this->oauthService->getAuthorizationUrl();

        return redirect($auth['url']);
    }

    /**
     * Handle callback when linking PadelGigs account from profile.
     */
    public function linkCallback(Request $request): RedirectResponse
    {
        $userId = session('padelgigs_link_user_id');
        session()->forget('padelgigs_link_user_id');

        if (! $userId || $userId !== Auth::id()) {
            return redirect()->route('profile.edit')->withErrors(['padelgigs' => 'Sesi penautan akun tidak valid.']);
        }

        try {
            $tokens = $this->oauthService->exchangeCodeForTokens(
                $request->input('code', ''),
                $request->input('state', '')
            );

            $userInfo = $this->oauthService->getUserInfo($tokens['access_token']);

            // Check if this PadelGigs account is already linked to another user
            $existing = User::where('padelgigs_user_id', $userInfo['id'])->first();
            if ($existing && $existing->id !== $userId) {
                return redirect()->route('profile.edit')->withErrors([
                    'padelgigs' => 'Akun PadelGigs ini sudah terhubung dengan akun pengguna lain.',
                ]);
            }

            $user = User::findOrFail($userId);
            $user->update([
                'padelgigs_user_id' => $userInfo['id'],
                'padelgigs_access_token' => $tokens['access_token'],
                'padelgigs_refresh_token' => $tokens['refresh_token'] ?? $user->padelgigs_refresh_token,
                'padelgigs_token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            ]);

            return redirect()->route('profile.edit')->with('success', 'Akun PadelGigs berhasil ditautkan.');

        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('profile.edit')->withErrors([
                'padelgigs' => 'Gagal menautkan akun PadelGigs: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Unlink PadelGigs account from profile.
     */
    public function unlink(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasLocalPassword()) {
            return redirect()->route('profile.edit')->withErrors([
                'padelgigs' => 'Silakan atur kata sandi terlebih dahulu sebelum memutuskan tautan PadelGigs.',
            ]);
        }

        $user->update([
            'padelgigs_user_id' => null,
            'padelgigs_access_token' => null,
            'padelgigs_refresh_token' => null,
            'padelgigs_token_expires_at' => null,
        ]);

        return redirect()->route('profile.edit')->with('success', 'Tautan akun PadelGigs berhasil dilepas.');
    }

    /**
     * Unified user resolution strategy:
     * 1. Check by padelgigs_user_id
     * 2. Check by email
     * 3. Check by phone number
     * 4. If exists, unify/link account (preserve existing store, products, roles, etc.)
     * 5. If not, create new User.
     */
    protected function findOrCreateUser(array $userInfo, array $tokens): User
    {
        $padelgigsId = (string) ($userInfo['id'] ?? '');
        $email = trim($userInfo['email'] ?? '');
        $name = trim($userInfo['name'] ?? 'User PadelGigs');
        $phone = $userInfo['phone'] ?? $userInfo['phone_number'] ?? null;
        $avatar = $userInfo['avatar'] ?? null;

        $isSellerMode = (bool) config('app.is_seller', false);

        // 1. Check by PadelGigs User ID
        if ($padelgigsId !== '') {
            $user = User::where('padelgigs_user_id', $padelgigsId)->first();
            if ($user) {
                $updates = [
                    'padelgigs_access_token' => $tokens['access_token'] ?? null,
                    'padelgigs_refresh_token' => $tokens['refresh_token'] ?? $user->padelgigs_refresh_token,
                    'padelgigs_token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : $user->padelgigs_token_expires_at,
                    'last_active_at' => now(),
                ];

                if ($isSellerMode && ! $user->is_seller) {
                    $updates['is_seller'] = true;
                    $updates['store_name'] = $user->store_name ?: ($user->name ? 'Toko '.$user->name : 'Toko Saya');
                    $updates['store_slug'] = $user->store_slug ?: Str::slug('toko-'.($user->name ?: 'seller').'-'.Str::random(5));
                }

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
                    'padelgigs_user_id' => $padelgigsId ?: null,
                    'padelgigs_access_token' => $tokens['access_token'] ?? null,
                    'padelgigs_refresh_token' => $tokens['refresh_token'] ?? $user->padelgigs_refresh_token,
                    'padelgigs_token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'phone_number' => $user->phone_number ?: $phone,
                    'avatar' => $user->avatar ?: $avatar,
                    'last_active_at' => now(),
                ];

                if ($isSellerMode && ! $user->is_seller) {
                    $updates['is_seller'] = true;
                    $updates['store_name'] = $user->store_name ?: ($user->name ? 'Toko '.$user->name : 'Toko Saya');
                    $updates['store_slug'] = $user->store_slug ?: Str::slug('toko-'.($user->name ?: 'seller').'-'.Str::random(5));
                }

                $user->update($updates);

                if ($user->roles()->count() === 0) {
                    $user->assignRole('Customer');
                }

                return $user;
            }
        }

        // 3. Check by Phone Number (Merge with existing store / customer)
        if ($phone) {
            $rawDigits = preg_replace('/\D/', '', (string) $phone);
            if ($rawDigits !== '') {
                $phoneVariations = array_filter(array_unique([
                    (string) $phone,
                    $rawDigits,
                    str_starts_with($rawDigits, '0') ? '62'.substr($rawDigits, 1) : null,
                    str_starts_with($rawDigits, '62') ? '0'.substr($rawDigits, 2) : null,
                    str_starts_with($rawDigits, '8') ? '08'.substr($rawDigits, 1) : null,
                    str_starts_with($rawDigits, '8') ? '628'.substr($rawDigits, 1) : null,
                ]));

                $user = User::whereIn('phone_number', $phoneVariations)->first();
                if ($user) {
                    $updates = [
                        'padelgigs_user_id' => $padelgigsId ?: null,
                        'padelgigs_access_token' => $tokens['access_token'] ?? null,
                        'padelgigs_refresh_token' => $tokens['refresh_token'] ?? $user->padelgigs_refresh_token,
                        'padelgigs_token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                        'avatar' => $user->avatar ?: $avatar,
                        'last_active_at' => now(),
                    ];

                    if ($isSellerMode && ! $user->is_seller) {
                        $updates['is_seller'] = true;
                        $updates['store_name'] = $user->store_name ?: ($user->name ? 'Toko '.$user->name : 'Toko Saya');
                        $updates['store_slug'] = $user->store_slug ?: Str::slug('toko-'.($user->name ?: 'seller').'-'.Str::random(5));
                    }

                    $user->update($updates);

                    if ($user->roles()->count() === 0) {
                        $user->assignRole('Customer');
                    }

                    return $user;
                }
            }
        }

        // 4. If completely new, create User (Role is always Customer; is_seller=true if IS_SELLER=true)
        $user = User::create([
            'padelgigs_user_id' => $padelgigsId ?: null,
            'name' => $name,
            'email' => $email,
            'phone_number' => $phone,
            'avatar' => $avatar,
            'password' => null,
            'is_active' => true,
            'is_seller' => $isSellerMode,
            'store_name' => $isSellerMode ? ($name ? 'Toko '.$name : 'Toko Saya') : null,
            'store_slug' => $isSellerMode ? Str::slug('toko-'.$name.'-'.Str::random(5)) : null,
            'email_verified_at' => now(),
            'padelgigs_access_token' => $tokens['access_token'] ?? null,
            'padelgigs_refresh_token' => $tokens['refresh_token'] ?? null,
            'padelgigs_token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            'last_active_at' => now(),
        ]);

        $user->assignRole('Customer');

        return $user;
    }
}
