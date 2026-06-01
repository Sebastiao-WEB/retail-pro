<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldForceHttps($request)) {
            $this->applyHttpsUrls();
        }

        return $next($request);
    }

    private function shouldForceHttps(Request $request): bool
    {
        if (config('app.force_https')) {
            return true;
        }

        if ($this->appIsProduction()) {
            return true;
        }

        if ($request->isSecure()) {
            return true;
        }

        if (strtolower((string) $request->header('X-Forwarded-Proto')) === 'https') {
            return true;
        }

        if (str_contains((string) $request->header('CF-Visitor', ''), 'https')) {
            return true;
        }

        return ! $this->isLocalHost($request->getHost());
    }

    private function appIsProduction(): bool
    {
        return app()->environment('production');
    }

    private function isLocalHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return true;
        }

        return str_ends_with($host, '.test') || str_ends_with($host, '.local');
    }

    private function applyHttpsUrls(): void
    {
        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'http://')) {
            $appUrl = 'https://'.substr($appUrl, 7);
        }

        if ($appUrl !== '') {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }

        URL::forceHttps();
    }
}
