<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProviderAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('portal.login'));
        }

        $user = Auth::guard('web')->user();

        if (! $user->providerDetails) {
            Auth::guard('web')->logout();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Provider profile required.'], 403);
            }

            return redirect()->route('portal.login')->withErrors(['login' => 'No provider profile linked to this account.']);
        }

        if (! $user->hasRole('provider')) {
            Auth::guard('web')->logout();

            return redirect()->route('portal.login')->withErrors(['login' => 'This account is not authorized for the provider portal.']);
        }

        return $next($request);
    }
}
