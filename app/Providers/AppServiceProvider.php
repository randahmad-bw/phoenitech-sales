<?php

namespace App\Providers;

use App\Http\Responses\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();

        // A super_admin passes every authorization check and can never be locked
        // out, even if a permission is removed from a role. Returning null (not
        // false) for everyone else lets the normal permission checks run.
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }

    /**
     * Define the named rate limiters used by the API routes.
     */
    private function configureRateLimiting(): void
    {
        // General API ceiling, keyed per authenticated user (or per IP when guest).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn () => ApiResponse::error(
                    'Too many requests. Please slow down.',
                    'TOO_MANY_REQUESTS',
                    429
                ));
        });

        // Login is keyed per email + IP so one attacker cannot lock out a whole office,
        // and cycling emails from a single IP does not buy extra attempts.
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip())
                    ->response(fn () => ApiResponse::error(
                        'Too many login attempts. Please try again in a minute.',
                        'TOO_MANY_LOGIN_ATTEMPTS',
                        429
                    )),
                Limit::perMinute(20)->by($request->ip())
                    ->response(fn () => ApiResponse::error(
                        'Too many login attempts from this address. Please try again later.',
                        'TOO_MANY_LOGIN_ATTEMPTS',
                        429
                    )),
            ];
        });
    }
}
