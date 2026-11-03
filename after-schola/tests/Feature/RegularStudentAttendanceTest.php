<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TrainerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 7a (TRN-06, BR-13, BR-14, AC-11): absensi siswa sesi kelas reguler dan kunci sesi.
 * Roster = siswa yang enrollment-nya mencakup tanggal sesi. Input oleh trainer pelaksana atau yang ditugaskan
 * di kelas; Management hanya baca. Buka kunci hanya pemegang "manage schedule".
 */
class RegularStudentAttendanceTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private ClassGroup $class;

    private ClassSession $session;

    private User $main;

    private User $assistant;

    private User $substitute;

    private User $unrelated;

    private User $coordinator;

    private Student $alice;

    private Student $bella;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->class = $this->classIn($this->bdg);
        $this->main = $this->trainer($this->bdg);
        $this->assistant = $this->trainer($this->bdg);
        $this->substitute = $this->trainer($this->bdg);
        $this->unrelated = $this->trainer($this->bdg);
        TrainerAssignment::create(['class_group_id' => $this->class->id, 'trainer_id' => $this->main->id, 'role' => 'utama']);
        TrainerAssignment::create(['class_group_id' => $this->class->id, 'trainer_id' => $this->assistant->id, 'role' => 'asisten']);
        $this->coordinator = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage schedule']);

        // Sesi 10 Nov 2026 dijalankan trainer utama; trainer pengganti hanya muncul di test yang memakainya.
        $this->session = ClassSession::create([
            'class_group_id' => $this->class->id, 'trainer_id' => $this->main->id, 'date' => '2026-11-10',
            'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => 1, 'mode' => 'onsite',
        ]);
        $this->alice = $this->enroll('Alice', '2026-10-01');
        $this->bella = $this->enroll('Bella', '2026-10-01');
    }

    private function classIn(Branch $branch): ClassGroup
    {
        $program = Program::create(['name' => 'Coding '.uniqid(), 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);

        return ClassGroup::create([
            'program_id' => $program->id, 'level_id' => $level->id, 'branch_id' => $branch->id,
            'name' => 'Kelas '.uniqid(), 'capacity' => 20,
        ]);
    }

    private function trainer(Branch $branch): User
    {
        return $this->makeStaff('trainer', 'assigned', [$branch->id]);
    }

    private function enroll(string $name, string $start, ?string $end = null, string $status = 'aktif', ?ClassGroup $class = null): Student
    {
        $student = Student::create(['name' => $name, 'branch_id' => $this->bdg->id]);
        Enrollment::create([
            'class_group_id' => ($class ?? $this->class)->id, 'student_id' => $student->id,
            'start_date' => $start, 'end_date' => $end, 'status' => $status,
        ]);

        return $student;
    }

    private function url(?ClassSession $session = null, string $suffix = 'student-attendances'): string
    {
        return '/api/class-sessions/'.($session ?? $this->session)->id.'/'.$suffix;
    }

    private function marks(array $rows): array
    {
        return ['attendances' => array_map(fn ($r) => ['student_id' => $r[0]->id, 'is_present' => $r[1]] + (isset($r[2]) ? ['note' => $r[2]] : []), $rows)];
    }

    public function test_roster_contains_only_students_enrolled_on_the_session_date(): void
    {
        $this->enroll('Dika', '2026-11-10');                               // mulai tepat di hari sesi: ikut
        $this->enroll('Eka', '2026-09-01', '2026-11-10', 'selesai');       // berakhir tepat di hari sesi: ikut
        $this->enroll('Fani', '2026-09-01', '2026-11-05', 'selesai');      // sudah berakhir: tidak ikut
        $this->enroll('Gita', '2026-11-11');                               // belum mulai: tidak ikut
        $this->enroll('Hana', '2026-10-01', null, 'aktif', $this->classIn($this->bdg)); // kelas lain: tidak ikut
        StudentAttendance::create(['class_session_id' => $this->session->id, 'student_id' => $this->alice->id, 'is_present' => true, 'note' => 'Tepat waktu']);

        Sanctum::actingAs($this->main);
        $res = $this->getJson($this->url())->assertOk();

        $this->assertSame(['Alice', 'Bella', 'Dika', 'Eka'], $res->json('data.*.name'));
        $res->assertJsonPath('data.0.is_present', true)->assertJsonPath('data.0.note', 'Tepat waktu')
            ->assertJsonPath('data.1.is_present', false)
            ->assertJsonPath('meta.total', 4)->assertJsonPath('meta.present_count', 1)->assertJsonPath('meta.is_locked', false);
    }

    public function test_marking_is_an_upsert_unique_per_session_and_student(): void
    {
        Sanctum::actingAs($this->main);

        $this->putJson($this->url(), $this->marks([[$this->alice, true, 'Hadir'], [$this->bella, false]]))
            ->assertOk()->assertJsonPath('meta.present_count', 1)->assertJsonPath('data.0.is_present', true);
        $this->putJson($this->url(), $this->marks([[$this->alice, false], [$this->bella, true]]))
            ->assertOk()->assertJsonPath('meta.present_count', 1)->assertJsonPath('data.1.is_present', true);

        $this->assertSame(2, StudentAttendance::count());
        $this->assertFalse(StudentAttendance::where('student_id', $this->alice->id)->value('is_present'));
        $this->assertNull(StudentAttendance::where('student_id', $this->alice->id)->value('note'));
    }

    public function test_assigned_and_substitute_trainers_may_mark_but_unrelated_trainers_get_404(): void
    {
        $this->session->update(['trainer_id' => $this->substitute->id]);

        foreach ([$this->assistant, $this->substitute, $this->main] as $trainer) {
            Sanctum::actingAs($trainer);
            $this->putJson($this->url(), $this->marks([[$this->alice, true]]))->assertOk();
        }

        Sanctum::actingAs($this->unrelated);
        $this->getJson($this->url())->assertNotFound();
        $this->putJson($this->url(), $this->marks([[$this->alice, false]]))->assertNotFound();
        $this->assertTrue(StudentAttendance::where('student_id', $this->alice->id)->value('is_present'));
    }

    public function test_students_outside_the_roster_are_rejected_and_nothing_is_saved(): void
    {
        Sanctum::actingAs($this->main);
        $ended = $this->enroll('Fani', '2026-09-01', '2026-11-05', 'selesai');
        $notStarted = $this->enroll('Gita', '2026-11-11');
        $otherClass = $this->enroll('Hana', '2026-10-01', null, 'aktif', $this->classIn($this->bdg));

        foreach ([$ended, $notStarted, $otherClass] as $outsider) {
            $this->putJson($this->url(), $this->marks([[$this->alice, true], [$outsider, true]]))
                ->assertStatus(422)->assertJsonValidationErrors('attendances');
        }
        $this->assertSame(0, StudentAttendance::count());
    }

    public function test_payload_is_validated(): void
    {
        Sanctum::actingAs($this->main);

        $this->putJson($this->url(), ['attendances' => []])->assertStatus(422)->assertJsonValidationErrors('attendances');
        $this->putJson($this->url(), ['attendances' => [['student_id' => $this->alice->id]]])->assertStatus(422)->assertJsonValidationErrors('attendances.0.is_present');
        $this->putJson($this->url(), $this->marks([[$this->alice, true, str_repeat('x', 256)]]))->assertStatus(422)->assertJsonValidationErrors('attendances.0.note');
        $this->putJson($this->url(), ['attendances' => [['student_id' => 999999, 'is_present' => true]]])->assertStatus(422);
    }

    public function test_locked_session_rejects_changes_but_can_still_be_read(): void
    {
        $this->session->update(['is_locked' => true]);
        Sanctum::actingAs($this->main);

        $this->putJson($this->url(), $this->marks([[$this->alice, true]]))->assertStatus(422)->assertJsonValidationErrors('session');
        $this->getJson($this->url())->assertOk()->assertJsonPath('meta.is_locked', true);
        $this->assertSame(0, StudentAttendance::count());
    }

    public function test_management_can_read_but_not_write_and_scope_and_roles_are_enforced(): void
    {
        $leadership = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['view all attendance']);
        $otherBranchStaff = $this->makeStaff('management', 'assigned', [$this->jkt->id], ['manage schedule']);

        foreach ([$this->coordinator, $leadership] as $viewer) {
            Sanctum::actingAs($viewer);
            $this->getJson($this->url())->assertOk();
            $this->putJson($this->url(), $this->marks([[$this->alice, true]]))->assertForbidden();
        }

        Sanctum::actingAs($otherBranchStaff);
        $this->getJson($this->url())->assertNotFound();
        $this->putJson($this->url(), $this->marks([[$this->alice, true]]))->assertNotFound();

        Sanctum::actingAs($this->makeStaff('parent'));
        $this->getJson($this->url())->assertForbidden();
        $this->putJson($this->url(), $this->marks([[$this->alice, true]]))->assertForbidden();
        $this->postJson($this->url(null, 'lock'))->assertForbidden();

        $this->assertSame(0, StudentAttendance::count());
    }

    public function test_view_all_attendance_alone_can_read_class_sessions_within_branch_scope(): void
    {
        $leadership = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['view all attendance']);
        $otherSession = ClassSession::create([
            'class_group_id' => $this->classIn($this->jkt)->id, 'trainer_id' => $this->trainer($this->jkt)->id, 'date' => '2026-11-10',
            'start_time' => '09:00', 'end_time' => '10:00', 'meeting_no' => 1, 'mode' => 'onsite',
        ]);

        Sanctum::actingAs($leadership);
        $this->assertEquals([$this->session->id], $this->getJson('/api/class-sessions')->assertOk()->json('data.*.id'));
        $this->getJson("/api/class-sessions/{$otherSession->id}")->assertNotFound();
        $this->getJson('/api/class-groups')->assertOk();
    }

    public function test_pelaksana_assigned_trainer_and_schedule_manager_can_lock_and_only_schedule_manager_can_unlock(): void
    {
        foreach ([$this->main, $this->assistant, $this->coordinator] as $locker) {
            $this->session->update(['is_locked' => false]);
            Sanctum::actingAs($locker);
            $this->postJson($this->url(null, 'lock'))->assertOk()->assertJsonPath('data.is_locked', true);
            $this->postJson($this->url(null, 'lock'))->assertOk(); // idempoten
        }

        Sanctum::actingAs($this->unrelated);
        $this->postJson($this->url(null, 'lock'))->assertNotFound();

        $leadership = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['view all attendance']);
        Sanctum::actingAs($leadership);
        $this->postJson($this->url(null, 'lock'))->assertForbidden();
        $this->postJson($this->url(null, 'unlock'))->assertForbidden();

        foreach ([$this->main, $this->assistant] as $trainer) {
            Sanctum::actingAs($trainer);
            $this->postJson($this->url(null, 'unlock'))->assertForbidden();
        }
        $this->assertTrue($this->session->fresh()->is_locked);

        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->jkt->id], ['manage schedule']));
        $this->postJson($this->url(null, 'unlock'))->assertNotFound();

        Sanctum::actingAs($this->coordinator);
        $this->postJson($this->url(null, 'unlock'))->assertOk()->assertJsonPath('data.is_locked', false);

        Sanctum::actingAs($this->main);
        $this->putJson($this->url(), $this->marks([[$this->alice, true]]))->assertOk();
    }

    public function test_legacy_school_endpoints_do_not_serve_class_group_sessions(): void
    {
        $management = $this->makeStaff('management', 'all', [], []);
        $id = $this->session->id;

        foreach ([$management, $this->main] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/sessions/{$id}")->assertNotFound();
            $this->getJson("/api/sessions/{$id}/student-attendances")->assertNotFound();
            $this->putJson("/api/sessions/{$id}/student-attendances", $this->marks([[$this->alice, true]]))->assertNotFound();
            $this->getJson("/api/sessions/{$id}/trainer-attendances")->assertNotFound();
            $this->postJson("/api/sessions/{$id}/trainer-attendances", ['status' => 'hadir'])->assertNotFound();
            $this->postJson("/api/sessions/{$id}/lock")->assertNotFound();
        }
        Sanctum::actingAs($management);
        $this->putJson("/api/sessions/{$id}", ['mode' => 'online'])->assertNotFound();
        $this->deleteJson("/api/sessions/{$id}")->assertNotFound();

        $this->assertSame('onsite', $this->session->fresh()->mode);
        $this->assertDatabaseHas('class_sessions', ['id' => $id]);
    }
}
