<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user || ! $user->can($permission)) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
