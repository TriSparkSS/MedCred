<?php

namespace App\Http\Middleware;

use App\Models\LoginLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackSellerApiActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $seller = $request->user();

        if ($seller) {
            LoginLog::touchCurrentSession($seller, 'seller', $request);
        }

        return $response;
    }
}
