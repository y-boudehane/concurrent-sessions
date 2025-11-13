<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmSessionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('broadcasting/auth')) {
            return $next($request);
        }

        if ($request->routeIs('confirm-device*')) {
            return $next($request);
        }



        // If current session is flagged as awaiting confirmation
        if ($request->session()->get('awaiting_confirmation')) {
            return redirect()->route('confirm-device');
        }
    }
}
