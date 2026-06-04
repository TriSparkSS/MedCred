<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('web')->user();

        if (! $admin) {
            abort(403, 'Unauthorized.');
        }

        if ($admin->status !== 'active') {
            Auth::guard('web')->logout();
            flash()->error('Your admin account is inactive. Please contact support.');

            return redirect()
                ->route('tenant.login')
                ->withErrors([
                    'account' => 'Your admin account is inactive. Please contact support.',
                ]);
        }

        return $next($request);
    }
}
