<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PracticeAuthMiddleware
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

        if (! $user->practice) {
            Auth::guard('web')->logout();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Practice profile required.'], 403);
            }

            return redirect()->route('portal.login')->withErrors(['login' => 'No practice profile linked to this account.']);
        }

        $practiceStatus = $user->practice->status instanceof \BackedEnum
            ? $user->practice->status->value
            : (string) $user->practice->status;

        if ((int) ($user->status ?? 1) !== 1 || $user->status === 'inactive' || $practiceStatus !== 'active') {
            Auth::guard('web')->logout();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Practice account is inactive.'], 403);
            }

            return redirect()->route('practice.login')->withErrors(['login' => 'Your practice account is inactive.']);
        }

        if (! $user->hasRole('practice')) {
            Auth::guard('web')->logout();

            return redirect()->route('portal.login')->withErrors(['login' => 'This account is not authorized for the practice portal.']);
        }

        return $next($request);
    }
}
