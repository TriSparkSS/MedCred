<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = auth()->guard('admin')->user();

        if (! $admin) {
            return redirect()->route('login');
        }

        // Legacy admins without roles retain full access until AdminPermissionSeeder runs
        if ($admin->roles()->count() === 0) {
            return $next($request);
        }

        if (! $admin->can($permission)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
