<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->shouldForceHttps()) {
            $this->configureHttpsUrls();
        }
    }

    private function shouldForceHttps(): bool
    {
        if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        if ($this->app->environment('production')) {
            return true;
        }

        if (str_starts_with((string) config('app.url'), 'https://')) {
            return true;
        }

        if (! $this->app->bound('request')) {
            return false;
        }

        /** @var Request $request */
        $request = $this->app->make('request');

        if ($request->isSecure()) {
            return true;
        }

        if (strtolower((string) $request->header('X-Forwarded-Proto')) === 'https') {
            return true;
        }

        $cfVisitor = (string) $request->header('CF-Visitor', '');

        return str_contains($cfVisitor, 'https');
    }

    private function configureHttpsUrls(): void
    {
        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'http://')) {
            $appUrl = 'https://'.substr($appUrl, 7);
        }

        if ($appUrl !== '') {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }

        URL::forceScheme('https');
    }
}
