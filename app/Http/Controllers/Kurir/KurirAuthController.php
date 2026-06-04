<?php

namespace App\Http\Controllers\Kurir;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class KurirAuthController extends Controller
{
    /**
     * Show the courier login page.
     */
    public function showLogin(): Response
    {
        return Inertia::render('Kurir/Login');
    }

    /**
     * Authenticate a courier user.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if (! $user->hasRole('Kurir Toko')) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Akun Anda tidak memiliki akses ke portal kurir.',
                ])->onlyInput('email');
            }

            return redirect()->route('kurir.dashboard');
        }

        return back()->withErrors([
            'email' => 'Email atau kata sandi yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    /**
     * Log the courier out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('kurir.login');
    }
}
