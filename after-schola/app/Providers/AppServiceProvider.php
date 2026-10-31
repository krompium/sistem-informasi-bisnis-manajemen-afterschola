<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
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
        // App API-only: tidak ada route web `password.reset`, jadi tautan reset diarahkan ke halaman SPA
        // (User::passwordResetUrl). Isi emailnya berbahasa Indonesia.
        ResetPassword::toMailUsing(function (User $user, string $token) {
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Atur ulang password AfterSchola')
                ->greeting('Halo!')
                ->line('Kami menerima permintaan untuk mengatur ulang password akun AfterSchola Anda.')
                ->action('Atur Ulang Password', $user->passwordResetUrl($token))
                ->line("Tautan ini berlaku selama {$minutes} menit.")
                ->line('Jika Anda tidak merasa memintanya, abaikan saja email ini. Password Anda tidak akan berubah.')
                ->salutation('Salam, Tim AfterSchola');
        });
    }
}
