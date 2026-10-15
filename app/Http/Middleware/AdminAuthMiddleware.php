<?php

namespace App\Http\Middleware;

use App\Models\LoginLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        $guard = $guard ?: 'admin';

        if (! Auth::guard($guard)->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        if ($guard === 'admin' && Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();

            if (filled($admin?->status) && $admin->status !== 'active') {
                Auth::guard('admin')->logout();

                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Account is inactive.'], 403);
                }

                return redirect()->route('login')->withErrors([
                    'login' => 'Your account is inactive. Please contact the administrator.',
                ]);
            }

            Auth::shouldUse('admin');
        }

        return $next($request);
    }
}
