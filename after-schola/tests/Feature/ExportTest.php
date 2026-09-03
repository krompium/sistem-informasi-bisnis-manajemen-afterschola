<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExportTest extends TestCase
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

    private function seedRecap(School $school, User $trainer): void
    {
        $level = Classroom::create(['school_id' => $school->id, 'name' => 'Beginner']);
        $ana = Student::create(['school_id' => $school->id, 'classroom_id' => $level->id, 'name' => 'Ana', 'origin_class' => '8A']);
        $budi = Student::create(['school_id' => $school->id, 'classroom_id' => $level->id, 'name' => 'Budi', 'origin_class' => '8B']);
        $session = ClassSession::create([
            'school_id' => $school->id, 'classroom_id' => $level->id, 'trainer_id' => $trainer->id,
            'mode' => 'onsite', 'date' => today(), 'meeting_no' => 1,
        ]);
        StudentAttendance::create(['class_session_id' => $session->id, 'student_id' => $ana->id, 'is_present' => true]);
        StudentAttendance::create(['class_session_id' => $session->id, 'student_id' => $budi->id, 'is_present' => false]);
    }

    public function test_management_can_export_excel(): void
    {
        $school = School::create(['name' => 'Sekolah X']);
        $trainer = $this->makeUser('trainer', [$school->id]);
        $this->seedRecap($school, $trainer);
        $management = $this->makeUser('management');

        $res = $this->actingAs($management, 'sanctum')
            ->get("/api/exports/attendance/excel?school_id={$school->id}");

        $res->assertOk()->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertNotEmpty($res->streamedContent());
    }

    public function test_management_can_export_pdf(): void
    {
        $school = School::create(['name' => 'Sekolah Y']);
        $trainer = $this->makeUser('trainer', [$school->id]);
        $this->seedRecap($school, $trainer);
        $management = $this->makeUser('management');

        $res = $this->actingAs($management, 'sanctum')
            ->get("/api/exports/attendance/pdf?school_id={$school->id}");

        $res->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_trainer_can_export_own_school_only(): void
    {
        $own = School::create(['name' => 'Milik']);
        $other = School::create(['name' => 'Bukan']);
        $trainer = $this->makeUser('trainer', [$own->id]);
        $this->seedRecap($own, $trainer);

        $this->actingAs($trainer, 'sanctum')
            ->get("/api/exports/attendance/excel?school_id={$own->id}")
            ->assertOk();

        $this->actingAs($trainer, 'sanctum')
            ->get("/api/exports/attendance/excel?school_id={$other->id}")
            ->assertStatus(403);
    }
}
