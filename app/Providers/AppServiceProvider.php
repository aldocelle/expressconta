<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontend = rtrim((string) config('app.frontend_url'), '/');
            return ($frontend ?: rtrim(config('app.url'), '/')).'/reset-password/'.rawurlencode($token).'?email='.rawurlencode($user->email);
        });
    }
}
