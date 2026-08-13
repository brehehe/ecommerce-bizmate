<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Login');
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $loginInput = trim($request->input('email'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        $attemptSuccess = false;

        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            $attemptSuccess = Auth::attempt(['email' => $loginInput, 'password' => $password], $remember);
        } else {
            $rawDigits = preg_replace('/\D/', '', $loginInput);

            $phoneVariations = array_filter(array_unique([
                $loginInput,
                $rawDigits,
                str_starts_with($rawDigits, '0') ? '62'.substr($rawDigits, 1) : null,
                str_starts_with($rawDigits, '62') ? '0'.substr($rawDigits, 2) : null,
                str_starts_with($rawDigits, '8') ? '08'.substr($rawDigits, 1) : null,
                str_starts_with($rawDigits, '8') ? '628'.substr($rawDigits, 1) : null,
            ]));

            $user = User::whereIn('phone_number', $phoneVariations)->first();

            if ($user && Hash::check($password, $user->password)) {
                Auth::login($user, $remember);
                $attemptSuccess = true;
            }
        }

        if ($attemptSuccess) {
            $user = Auth::user();

            if ($user->hasRole('Customer') && ! $user->hasVerifiedEmail()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Akun Anda belum diverifikasi. Silakan periksa email Anda.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            if (! $user->hasRole('Customer')) {
                return redirect()->intended('/admin');
            }

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'Email / No. HP atau kata sandi yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
