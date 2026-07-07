<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsKurir
{
    /**
     * Handle an incoming request.
     *
     * Only users with the 'Kurir Toko' role may pass.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasRole('Kurir Toko')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            return redirect()->route('kurir.login');
        }

        return $next($request);
    }
}
