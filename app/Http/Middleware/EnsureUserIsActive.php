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
        $user = Auth::guard('web')->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        if (! in_array($user->status, ['active', 1, '1', true], true)) {
            Auth::guard('web')->logout();
            flash()->error('Your account is inactive. Please contact support.');

            return redirect()
                ->route('portal.login')
                ->withErrors([
                    'account' => 'Your account is inactive. Please contact support.',
                ]);
        }

        return $next($request);
    }
}
