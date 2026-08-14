<?php

namespace App\Http\Middleware;

use App\Support\LocaleManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationLocale
{
    public function __construct(
        protected LocaleManager $localeManager
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $defaultLocale = config('localization.default', config('app.locale', 'en'));

        if ($request->is('api/*')) {
            $fallback = $request->user()?->preferred_language ?? $defaultLocale;
            $locale = $this->localeManager->resolveFromRequest($request, $fallback);
        } else {
            $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
            $fallback = $request->user()?->preferred_language ?? $defaultLocale;
            $locale = $sessionLocale && $this->localeManager->isSupported($sessionLocale)
                ? $sessionLocale
                : $this->localeManager->resolveFromRequest($request, $fallback);
        }

        App::setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
