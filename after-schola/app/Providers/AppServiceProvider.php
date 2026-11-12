<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        // NFR-03: 5 percobaan per menit untuk tiap kombinasi endpoint, email, dan IP. Per email supaya satu akun yang dibrute-force
        // tidak mengunci rekan sekantor di Wi-Fi yang sama (satu IP).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->path().'|'.Str::lower((string) $request->input('email')).'|'.$request->ip()));
    }
}
