<?php

namespace App\Providers;

use App\Models\User;
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
        // App API-only: tidak ada route web `password.reset`, jadi tautan reset diarahkan ke halaman SPA.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => $user->passwordResetUrl($token));
    }
}
