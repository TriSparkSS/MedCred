<?php

namespace App\Http\Middleware;

use App\Models\LoginLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackCustomerApiActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user) {
            LoginLog::touchCurrentSession($user, 'customer', $request);
        }

        return $response;
    }
}
