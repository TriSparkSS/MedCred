<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\AdminAuthMiddleware;
use App\Http\Middleware\EnsurePortalPermission;
use App\Http\Middleware\PracticeAuthMiddleware;
use App\Http\Middleware\ProviderAuthMiddleware;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\UseAdminGuardWhenAuthenticated::class,
        ]);

        $middleware->alias([
            'is_auth' => AdminAuthMiddleware::class,
            'admin.permission' => \App\Http\Middleware\EnsureAdminPermission::class,
            'provider_auth' => ProviderAuthMiddleware::class,
            'practice_auth' => PracticeAuthMiddleware::class,
            'portal.permission' => EnsurePortalPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
