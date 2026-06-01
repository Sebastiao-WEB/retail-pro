<?php

namespace App\Providers;

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
        if ($this->shouldForceHttpsAtBoot()) {
            $this->applyHttpsUrls();
        }
    }

    private function shouldForceHttpsAtBoot(): bool
    {
        if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        if ($this->app->environment('production')) {
            return true;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '';

        return $host !== '' && ! $this->isLocalHost($host);
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
