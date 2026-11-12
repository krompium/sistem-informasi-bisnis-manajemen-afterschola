<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * NFR-03: login, lupa password, dan reset password dibatasi lajunya (cegah tebak password).
 * Batas per kombinasi email, IP, dan endpoint: 5 percobaan per menit.
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Batas berlaku per jendela 60 detik. Waktu dibekukan supaya hasil test tidak bergantung pada kecepatan mesin,
        // dan notifikasi reset password tidak benar-benar dikirim.
        $this->freezeTime();
        Notification::fake();
    }

    private function wrongLogin(string $email)
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => 'salah-terus']);
    }

    public function test_login_is_blocked_after_five_attempts_for_the_same_email(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->wrongLogin($user->email)->assertUnprocessable();
        }

        $this->wrongLogin($user->email)->assertStatus(429);
        // Bahkan password yang benar ditolak selama terkunci.
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    }

    public function test_one_locked_out_email_does_not_block_other_users_on_the_same_network(): void
    {
        $locked = User::factory()->create();
        $other = User::factory()->create();
        foreach (range(1, 6) as $attempt) {
            $this->wrongLogin($locked->email);
        }

        $this->postJson('/api/login', ['email' => $other->email, 'password' => 'password'])->assertOk();
    }

    public function test_a_correct_login_within_the_limit_still_works(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 4) as $attempt) {
            $this->wrongLogin($user->email)->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_the_limit_resets_after_a_minute(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $attempt) {
            $this->wrongLogin($user->email);
        }
        $this->wrongLogin($user->email)->assertStatus(429);

        $this->travel(61)->seconds();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_forgot_and_reset_password_are_limited_independently(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/forgot-password', ['email' => $user->email]);
            $this->postJson('/api/reset-password', ['email' => $user->email, 'token' => 'x', 'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1']);
        }

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertStatus(429);
        $this->postJson('/api/reset-password', ['email' => $user->email, 'token' => 'x', 'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1'])->assertStatus(429);
    }
}
