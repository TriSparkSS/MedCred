<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UseAdminGuardWhenAuthenticated
{
    /**
     * Ensure Livewire/sub-requests resolve Auth::user() to the admin guard
     * when an admin session is active (admin panel uses the admin guard, not web).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check() && ! Auth::guard('web')->check()) {
            Auth::shouldUse('admin');
        }

        return $next($request);
    }
}
