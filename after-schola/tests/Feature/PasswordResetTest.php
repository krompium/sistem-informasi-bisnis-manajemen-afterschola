<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_registered_email_gets_reset_link_that_points_to_frontend(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.afterschola.test']);
        $user = $this->makeStaff('parent');

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);

            return str_starts_with($mail->actionUrl, 'https://app.afterschola.test/reset-password?token=')
                && str_contains($mail->actionUrl, 'email='.urlencode($user->email))
                && $mail->subject === 'Atur ulang password AfterSchola';
        });
    }

    public function test_unknown_email_gets_same_response_so_accounts_cannot_be_guessed(): void
    {
        Notification::fake();
        $known = $this->makeStaff('parent');

        $a = $this->postJson('/api/forgot-password', ['email' => $known->email])->assertOk()->json('message');
        $b = $this->postJson('/api/forgot-password', ['email' => 'tidak-ada@example.com'])->assertOk()->json('message');

        $this->assertSame($a, $b);
        Notification::assertCount(1);
    }

    public function test_forgot_password_rejects_malformed_email(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'bukan-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_forgot_password_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/forgot-password', ['email' => 'x@example.com'])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'x@example.com'])->assertStatus(429);
    }

    public function test_valid_token_changes_password_and_revokes_old_api_tokens(): void
    {
        $user = $this->makeStaff('parent');
        $user->createToken('perangkat-lama');
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertOk()->assertJsonStructure(['message']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password-baru-123'])->assertOk();
    }

    public function test_invalid_token_is_rejected_with_validation_error(): void
    {
        $user = $this->makeStaff('parent');

        $this->postJson('/api/reset-password', [
            'token' => 'token-ngawur',
            'email' => $user->email,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        $user = $this->makeStaff('parent');
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'pendek', 'password_confirmation' => 'pendek',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'password-baru-123', 'password_confirmation' => 'beda-sendiri-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
