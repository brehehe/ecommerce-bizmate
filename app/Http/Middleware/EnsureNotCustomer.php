<?php

namespace App\Http\Middleware;

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
        if ($request->user() && $request->user()->hasRole('Customer')) {
            return redirect('/')->with('error', 'Anda tidak memiliki hak akses untuk halaman Admin.');
        }

        return $next($request);
    }
}
