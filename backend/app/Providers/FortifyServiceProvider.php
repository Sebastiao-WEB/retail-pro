<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(function (Request $request) {
            $credentials = $request->validate([
                'username' => ['required', 'string'],
                'password' => ['required', 'string'],
            ]);

            Log::info('Login attempt', [
                'username' => $credentials['username'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $user = User::query()
                ->where('username', $credentials['username'])
                ->first();

            if ($user && Hash::check($credentials['password'], $user->password) && ! $user->is_active) {
                Log::warning('Login failed', [
                    'username' => $credentials['username'],
                    'ip' => $request->ip(),
                    'reason' => 'user_inactive',
                ]);

                throw ValidationException::withMessages([
                    Fortify::username() => [EnsureUserIsActive::SUSPENDED_MESSAGE],
                ]);
            }

            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                Log::warning('Login failed', [
                    'username' => $credentials['username'],
                    'ip' => $request->ip(),
                    'reason' => ! $user ? 'user_not_found' : 'invalid_password',
                ]);

                throw ValidationException::withMessages([
                    Fortify::username() => ['Credenciais inválidas.'],
                ]);
            }

            Auth::guard('web')->login($user, $request->boolean('remember'));

            Log::info('Login successful', [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip' => $request->ip(),
            ]);

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
