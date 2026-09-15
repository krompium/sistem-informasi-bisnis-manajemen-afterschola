<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AbsensiFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(string $role, array $schoolIds = []): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.'_'.uniqid().'@test.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        if ($schoolIds) {
            $user->assignedSchools()->sync($schoolIds);
        }

        return $user;
    }

    public function test_login_returns_token_and_roles(): void
    {
        $user = $this->makeUser('management');

        $res = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'roles', 'permissions']])
            ->assertJsonPath('user.roles.0', 'management');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeUser('trainer');
        $user->update(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_trainer_only_sees_assigned_schools(): void
    {
        $s1 = School::create(['name' => 'Sekolah A']);
        $s2 = School::create(['name' => 'Sekolah B']);
        $trainer = $this->makeUser('trainer', [$s1->id]);

        $res = $this->actingAs($trainer, 'sanctum')->getJson('/api/schools');

        $res->assertOk();
        $names = collect($res->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Sekolah A'));
        $this->assertFalse($names->contains('Sekolah B'));
    }

    public function test_trainer_can_add_student_only_in_own_school(): void
    {
        $own = School::create(['name' => 'Milik']);
        $other = School::create(['name' => 'Bukan Milik']);
        $ownLevel = Classroom::create(['school_id' => $own->id, 'name' => 'Beginner']);
        $otherLevel = Classroom::create(['school_id' => $other->id, 'name' => 'Beginner']);
        $trainer = $this->makeUser('trainer', [$own->id]);

        // Boleh: sekolah sendiri.
        $this->actingAs($trainer, 'sanctum')->postJson('/api/students', [
            'school_id' => $own->id,
            'classroom_id' => $ownLevel->id,
            'name' => 'Budi',
            'origin_class' => '8A',
        ])->assertStatus(201);

        // Ditolak: sekolah lain.
        $this->actingAs($trainer, 'sanctum')->postJson('/api/students', [
            'school_id' => $other->id,
            'classroom_id' => $otherLevel->id,
            'name' => 'Sisca',
        ])->assertStatus(422);
    }

    public function test_trainer_cannot_create_session_but_management_can(): void
    {
        $school = School::create(['name' => 'Sekolah C']);
        $level = Classroom::create(['school_id' => $school->id, 'name' => 'Beginner']);
        $trainer = $this->makeUser('trainer', [$school->id]);
        $management = $this->makeUser('management');

        $payload = [
            'school_id' => $school->id,
            'classroom_id' => $level->id,
            'trainer_id' => $trainer->id,
            'mode' => 'online',
            'date' => today()->toDateString(),
            'meeting_no' => 1,
        ];

        $this->actingAs($trainer, 'sanctum')->postJson('/api/sessions', $payload)->assertStatus(403);
        $this->actingAs($management, 'sanctum')->postJson('/api/sessions', $payload)->assertStatus(201);
    }

    public function test_student_attendance_sync_and_lock(): void
    {
        $school = School::create(['name' => 'Sekolah D']);
        $level = Classroom::create(['school_id' => $school->id, 'name' => 'Beginner']);
        $trainer = $this->makeUser('trainer', [$school->id]);
        $student = Student::create([
            'school_id' => $school->id, 'classroom_id' => $level->id, 'name' => 'Ana',
        ]);
        $session = ClassSession::create([
            'school_id' => $school->id, 'classroom_id' => $level->id, 'trainer_id' => $trainer->id,
            'mode' => 'online', 'date' => today(), 'meeting_no' => 1,
        ]);

        // Simpan absensi.
        $this->actingAs($trainer, 'sanctum')->putJson("/api/sessions/{$session->id}/student-attendances", [
            'attendances' => [
                ['student_id' => $student->id, 'is_present' => true],
            ],
        ])->assertOk()->assertJsonPath('present_count', 1);

        // Kunci pertemuan.
        $this->actingAs($trainer, 'sanctum')->postJson("/api/sessions/{$session->id}/lock")
            ->assertOk()->assertJsonPath('data.is_locked', true);

        // Setelah dikunci, input ditolak.
        $this->actingAs($trainer, 'sanctum')->putJson("/api/sessions/{$session->id}/student-attendances", [
            'attendances' => [
                ['student_id' => $student->id, 'is_present' => false],
            ],
        ])->assertStatus(403);
    }

    public function test_trainer_online_checkin_without_gps(): void
    {
        $school = School::create(['name' => 'Sekolah E']);
        $level = Classroom::create(['school_id' => $school->id, 'name' => 'Beginner']);
        $trainer = $this->makeUser('trainer', [$school->id]);
        $session = ClassSession::create([
            'school_id' => $school->id, 'classroom_id' => $level->id, 'trainer_id' => $trainer->id,
            'mode' => 'online', 'date' => today(), 'meeting_no' => 1,
        ]);

        $this->actingAs($trainer, 'sanctum')->postJson("/api/sessions/{$session->id}/trainer-attendances", [
            'status' => 'hadir',
        ])->assertStatus(201)->assertJsonPath('data.status', 'hadir');
    }

    public function test_only_management_can_manage_users(): void
    {
        $trainer = $this->makeUser('trainer');
        $management = $this->makeUser('management');

        $this->actingAs($trainer, 'sanctum')->getJson('/api/users')->assertStatus(403);
        $this->actingAs($management, 'sanctum')->getJson('/api/users')->assertOk();
    }
}
