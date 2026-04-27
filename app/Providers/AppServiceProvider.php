<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
        // When the request binding is refreshed (e.g. between test requests or during
        // middleware pipelines), reset the Sanctum guard's cached user so that token
        // revocation is honoured on the very next request without stale guard state.
        $this->app->rebinding('request', function () {
            if ($this->app->resolved('auth')) {
                $this->app['auth']->forgetGuards();
            }
        });

        // This is an API-only application — there is no web `password.reset` named route.
        // Override the reset URL to point to the frontend app so the notification does not
        // throw RouteNotFoundException when Password::sendResetLink() fires for a known email.
        // FRONTEND_URL defaults to http://localhost:3000 (Next.js dev server).
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $base = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');

            return $base . '/reset-password?token=' . $token . '&email=' . urlencode($user->getEmailForPasswordReset());
        });
    }
}
