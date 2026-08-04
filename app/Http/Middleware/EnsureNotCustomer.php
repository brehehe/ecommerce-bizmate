<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect('/login');
        }

        $isAdmin = $user->hasAnyRole(['Super Admin', 'Admin']);
        $isSellerEnabled = filter_var(
            Setting::where('key', 'is_seller')->value('value') ?? config('app.is_seller', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $isAdmin) {
            if (! $isSellerEnabled || ($user->hasRole('Customer') && ! $user->is_seller)) {
                return redirect('/')->with('error', 'Fitur penjual sedang dinonaktifkan.');
            }
        }

        return $next($request);
    }
}
