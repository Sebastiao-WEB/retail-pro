<?php

namespace App\Http\Middleware;

use App\Support\SupportedLocales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(SupportedLocales::COOKIE_NAME, SupportedLocales::DEFAULT);

        if (! SupportedLocales::isValid($locale)) {
            $locale = SupportedLocales::DEFAULT;
        }

        App::setLocale($locale);
        Carbon::setLocale(SupportedLocales::carbonLocale($locale));

        return $next($request);
    }
}
