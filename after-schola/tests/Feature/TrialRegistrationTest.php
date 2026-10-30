<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\TrialSlot;
use App\Models\User;
use App\Notifications\TrialRegisteredNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 2, slice 2.3: form trial publik (PUB-03..07, AC-01). Pendaftaran membuat akun Orangtua,
 * lead, dan memesan slot berkapasitas. Penolakan aturan bisnis = ValidationException (422).
 */
class TrialRegistrationTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private const URL = '/api/public/trial-registrations';

    private Branch $branch;

    private Program $program;

    private TrialSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->branch = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgram('Coding Kids');
        $this->slot = $this->makeSlot();
    }

    private function makeProgram(string $name, bool $active = true): Program
    {
        return Program::create(['name' => $name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => $active]);
    }

    private function makeSlot(array $overrides = []): TrialSlot
    {
        $branchId = $overrides['branch_id'] ?? $this->branch->id;

        return TrialSlot::create(array_merge([
            'branch_id' => $branchId,
            'program_id' => $this->program->id,
            'trainer_id' => $this->makeStaff('trainer', 'assigned', [$branchId])->id,
            'starts_at' => now()->addDays(3),
            'capacity' => 3,
            'is_open' => true,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'parent_name' => 'Siti Aminah',
            'parent_email' => 'siti@example.com',
            'parent_phone' => '0812-3456-7890',
            'name' => 'Budi Santoso',
            'education_level' => 'smp',
            'origin_school' => 'SMP Negeri 5 Bandung',
            'trial_slot_id' => $this->slot->id,
            'consent' => true,
        ], $overrides);
    }

    /** Token atur-password yang dikirim lewat email pendaftaran. */
    private function tokenFromEmail(User $parent): string
    {
        $token = null;
        Notification::assertSentTo($parent, TrialRegisteredNotification::class, function ($notification) use ($parent, &$token) {
            parse_str((string) parse_url($notification->toMail($parent)->actionUrl, PHP_URL_QUERY), $query);
            $token = $query['token'];

            return true;
        });

        return $token;
    }

    public function test_valid_registration_creates_parent_account_and_lead(): void
    {
        Notification::fake();

        $this->postJson(self::URL, $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Budi Santoso')
            ->assertJsonPath('data.status', 'trial_scheduled')
            ->assertJsonPath('data.trial_slot_id', $this->slot->id)
            ->assertJsonPath('data.program_id', $this->program->id)
            ->assertJsonPath('data.trial_slot.program', 'Coding Kids')
            ->assertJsonPath('data.trial_slot.branch', 'Cabang Bandung')
            ->assertJsonPath('data.trial_slot.starts_at', $this->slot->starts_at->toIso8601String())
            ->assertJsonMissingPath('data.note')
            ->assertJsonMissingPath('data.parent');

        $parent = User::where('email', 'siti@example.com')->firstOrFail();
        $this->assertTrue($parent->hasRole('parent'));
        $this->assertTrue($parent->is_active);
        $this->assertSame('Siti Aminah', $parent->name);
        $this->assertSame('0812-3456-7890', $parent->phone);
        $this->assertNotEmpty($parent->password);

        $this->assertDatabaseHas('leads', [
            'name' => 'Budi Santoso',
            'parent_id' => $parent->id,
            'parent_email' => 'siti@example.com',
            'branch_id' => $this->branch->id,
            'program_id' => $this->program->id,
            'trial_slot_id' => $this->slot->id,
            'status' => 'trial_scheduled',
        ]);
        $this->assertNotNull(Lead::firstOrFail()->consented_at);
    }

    public function test_parent_gets_confirmation_with_a_valid_password_setup_link(): void
    {
        Notification::fake();
        $this->postJson(self::URL, $this->payload())->assertStatus(201);
        $parent = User::where('email', 'siti@example.com')->firstOrFail();

        Notification::assertSentTo($parent, TrialRegisteredNotification::class, function ($notification, $channels) use ($parent) {
            // database lebih dulu: notifikasi in-app tetap tersimpan walau email gagal terkirim (R-04).
            $this->assertSame(['database', 'mail'], $channels);

            $mail = $notification->toMail($parent);
            $this->assertSame('Pendaftaran trial diterima', $mail->subject);
            $text = implode(' ', array_merge([$mail->greeting], $mail->introLines, $mail->outroLines));
            foreach (['Siti Aminah', 'Budi Santoso', 'Coding Kids', 'Cabang Bandung', 'WIB'] as $expected) {
                $this->assertStringContainsString($expected, $text);
            }

            $this->assertStringStartsWith(rtrim(config('app.frontend_url'), '/').'/reset-password?', $mail->actionUrl);
            parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
            $this->assertSame('siti@example.com', $query['email']);
            $this->assertTrue(Password::tokenExists($parent, $query['token']));
            $this->assertArrayNotHasKey('token', $notification->toArray($parent));

            return true;
        });
    }

    public function test_the_emailed_link_lets_the_parent_set_a_password_and_log_in(): void
    {
        Notification::fake();
        $this->postJson(self::URL, $this->payload())->assertStatus(201);
        $parent = User::where('email', 'siti@example.com')->firstOrFail();

        $this->postJson('/api/reset-password', [
            'token' => $this->tokenFromEmail($parent),
            'email' => 'siti@example.com',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
        ])->assertOk();

        $this->postJson('/api/login', ['email' => 'siti@example.com', 'password' => 'rahasia-baru-123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    // Link reset bawaan Laravel memakai route web `password.reset` yang tidak ada di app API-only (500).
    public function test_forgot_password_email_links_to_the_spa(): void
    {
        Notification::fake();
        $user = $this->makeStaff('parent');

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith(rtrim(config('app.frontend_url'), '/').'/reset-password?token=', $url);
            $this->assertStringContainsString('email='.urlencode($user->email), $url);

            return true;
        });
    }

    public function test_notification_failure_does_not_break_the_registration(): void
    {
        Log::spy();
        $this->mock(Dispatcher::class, fn ($mock) => $mock->shouldReceive('send')->andThrow(new \RuntimeException('smtp down')));

        $this->postJson(self::URL, $this->payload())->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'siti@example.com']);
        $this->assertDatabaseCount('leads', 1);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_existing_email_is_rejected_with_a_login_hint_and_creates_nothing(): void
    {
        foreach (['parent', 'management'] as $role) {
            $existing = $this->makeStaff($role);
            $users = User::count();

            $res = $this->postJson(self::URL, $this->payload(['parent_email' => $existing->email]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('parent_email');

            $this->assertStringContainsString('masuk', $res->json('errors.parent_email.0'));
            $this->assertSame($users, User::count());
        }

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_full_slot_is_rejected_and_leaves_no_account_behind(): void
    {
        Notification::fake();
        $slot = $this->makeSlot(['capacity' => 2]);

        foreach (['a', 'b'] as $key) {
            $this->postJson(self::URL, $this->payload(['trial_slot_id' => $slot->id, 'parent_email' => "{$key}@example.com"]))
                ->assertStatus(201);
        }
        $users = User::count();

        $this->postJson(self::URL, $this->payload(['trial_slot_id' => $slot->id, 'parent_email' => 'c@example.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('trial_slot_id');

        $this->assertSame($users, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'c@example.com']);
        $this->assertDatabaseCount('leads', 2);
    }

    public function test_closed_past_archived_and_inactive_branch_slots_are_rejected(): void
    {
        $archived = $this->makeProgram('Program Arsip', active: false);
        $closedBranch = $this->makeBranch('Cabang Tutup', 'TTP', ['is_active' => false]);
        $unavailable = [
            $this->makeSlot(['is_open' => false]),
            $this->makeSlot(['starts_at' => now()->subHour()]),
            $this->makeSlot(['program_id' => $archived->id]),
            $this->makeSlot(['branch_id' => $closedBranch->id]),
        ];
        $users = User::count();

        foreach ($unavailable as $slot) {
            $this->postJson(self::URL, $this->payload(['trial_slot_id' => $slot->id]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('trial_slot_id');
        }

        $this->assertSame($users, User::count());
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_slot_is_required_and_must_exist(): void
    {
        $this->postJson(self::URL, $this->payload(['trial_slot_id' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('trial_slot_id');
        $this->postJson(self::URL, $this->payload(['trial_slot_id' => 999999]))
            ->assertStatus(422)->assertJsonValidationErrors('trial_slot_id');

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_all_fields_are_required(): void
    {
        $this->postJson(self::URL, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'parent_name', 'parent_email', 'parent_phone', 'name',
                'education_level', 'origin_school', 'trial_slot_id', 'consent',
            ]);
    }

    public function test_field_formats_and_consent_are_validated(): void
    {
        $cases = [
            'parent_email' => 'bukan-email',
            'parent_phone' => 'abc',
            'education_level' => 'kuliah',
            'consent' => false,
        ];

        foreach ($cases as $field => $value) {
            $this->postJson(self::URL, $this->payload([$field => $value]))
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseMissing('users', ['email' => 'siti@example.com']);
    }

    public function test_program_id_from_the_client_is_ignored_in_favour_of_the_slot(): void
    {
        Notification::fake();
        $other = $this->makeProgram('Robotik');

        $this->postJson(self::URL, $this->payload(['program_id' => $other->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.program_id', $this->program->id);
    }

    public function test_rate_limit_blocks_after_five_requests(): void
    {
        Notification::fake();
        $slot = $this->makeSlot(['capacity' => 10]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::URL, $this->payload(['trial_slot_id' => $slot->id, 'parent_email' => "ortu{$i}@example.com"]))
                ->assertStatus(201);
        }

        $this->postJson(self::URL, $this->payload(['trial_slot_id' => $slot->id, 'parent_email' => 'ortu6@example.com']))
            ->assertStatus(429);
    }
}
