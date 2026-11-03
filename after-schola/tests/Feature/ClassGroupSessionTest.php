<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\School;
use App\Models\Student;
use App\Models\TrainerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 6c (MGT-34/35/36): sesi dan jadwal kelas reguler. BR-12 (trainer tidak boleh bentrok),
 * trainer pengganti minimal (pelaksana boleh berbeda dari penugasan), isolasi cabang dan penugasan.
 */
class ClassGroupSessionTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private ClassGroup $class;

    private User $staff;

    private User $assigned;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->class = $this->classIn($this->bdg);
        $this->assigned = $this->trainer($this->bdg);
        TrainerAssignment::create(['class_group_id' => $this->class->id, 'trainer_id' => $this->assigned->id, 'role' => 'utama']);
        $this->staff = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage schedule']);
        Sanctum::actingAs($this->staff);
    }

    private function classIn(Branch $branch, array $overrides = []): ClassGroup
    {
        $program = Program::create(['name' => 'Coding '.uniqid(), 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);

        return ClassGroup::create($overrides + [
            'program_id' => $program->id, 'level_id' => $level->id, 'branch_id' => $branch->id,
            'name' => 'Kelas '.uniqid(), 'capacity' => 10,
        ]);
    }

    private function trainer(Branch $branch): User
    {
        return $this->makeStaff('trainer', 'assigned', [$branch->id]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'class_group_id' => $this->class->id,
            'trainer_id' => $this->assigned->id,
            'date' => '2026-11-10',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'meeting_no' => 1,
            'mode' => 'onsite',
        ];
    }

    private function sessionOf(ClassGroup $class, User $trainer, array $overrides = []): ClassSession
    {
        return ClassSession::create($overrides + [
            'class_group_id' => $class->id, 'trainer_id' => $trainer->id, 'date' => '2026-11-10',
            'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => 1, 'mode' => 'onsite',
        ]);
    }

    public function test_staff_can_schedule_a_session_for_an_assigned_trainer(): void
    {
        $this->postJson('/api/class-sessions', $this->payload(['note' => 'Pertemuan perdana']))->assertCreated()
            ->assertJsonPath('data.class_group.id', $this->class->id)
            ->assertJsonPath('data.trainer.id', $this->assigned->id)
            ->assertJsonPath('data.date', '2026-11-10')
            ->assertJsonPath('data.start_time', '09:00')
            ->assertJsonPath('data.end_time', '10:30')
            ->assertJsonPath('data.meeting_no', 1)
            ->assertJsonPath('data.mode', 'onsite')
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.is_substitute', false);

        $this->assertDatabaseHas('class_sessions', [
            'class_group_id' => $this->class->id, 'school_id' => null, 'classroom_id' => null, 'note' => 'Pertemuan perdana',
        ]);
    }

    public function test_validation_of_time_mode_meeting_and_class(): void
    {
        $this->postJson('/api/class-sessions', $this->payload(['start_time' => '10:00', 'end_time' => '09:00']))
            ->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->postJson('/api/class-sessions', $this->payload(['end_time' => '09:00']))->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->postJson('/api/class-sessions', $this->payload(['mode' => 'hybrid']))->assertStatus(422)->assertJsonValidationErrors('mode');
        $this->postJson('/api/class-sessions', $this->payload(['meeting_no' => 0]))->assertStatus(422)->assertJsonValidationErrors('meeting_no');
        $this->postJson('/api/class-sessions', $this->payload(['date' => 'besok']))->assertStatus(422)->assertJsonValidationErrors('date');
        $this->postJson('/api/class-sessions', $this->payload(['class_group_id' => 999999]))->assertStatus(422)->assertJsonValidationErrors('class_group_id');

        $archived = $this->classIn($this->bdg, ['is_active' => false]);
        $this->postJson('/api/class-sessions', $this->payload(['class_group_id' => $archived->id]))
            ->assertStatus(422)->assertJsonValidationErrors('class_group_id');

        $this->assertSame(0, ClassSession::count());
    }

    public function test_substitute_trainer_is_allowed_when_active_and_in_the_class_branch(): void
    {
        $substitute = $this->trainer($this->bdg); // tidak ditugaskan ke kelas ini

        $this->postJson('/api/class-sessions', $this->payload(['trainer_id' => $substitute->id]))->assertCreated()
            ->assertJsonPath('data.trainer.id', $substitute->id)
            ->assertJsonPath('data.is_substitute', true);
    }

    public function test_only_active_trainers_with_access_to_the_class_branch_can_run_a_session(): void
    {
        $notTrainer = $this->makeStaff('management', 'assigned', [$this->bdg->id]);
        $inactive = $this->trainer($this->bdg);
        $inactive->update(['is_active' => false]);
        $otherBranch = $this->trainer($this->jkt);

        foreach ([$notTrainer, $inactive, $otherBranch] as $invalid) {
            $this->postJson('/api/class-sessions', $this->payload(['trainer_id' => $invalid->id]))
                ->assertStatus(422)->assertJsonValidationErrors('trainer_id');
        }
        $this->assertSame(0, ClassSession::count());
    }

    public function test_overlapping_sessions_of_the_same_trainer_are_rejected(): void
    {
        $this->sessionOf($this->class, $this->assigned); // 09:00-10:30

        foreach ([['09:30', '10:00'], ['08:00', '09:15'], ['10:00', '11:00'], ['08:00', '12:00']] as [$start, $end]) {
            $this->postJson('/api/class-sessions', $this->payload(['start_time' => $start, 'end_time' => $end, 'meeting_no' => 2]))
                ->assertStatus(422)->assertJsonValidationErrors('trainer_id');
        }
        $this->assertSame(1, ClassSession::count());
    }

    public function test_back_to_back_other_day_and_other_trainer_do_not_clash(): void
    {
        $this->sessionOf($this->class, $this->assigned); // 09:00-10:30
        $other = $this->trainer($this->bdg);

        $this->postJson('/api/class-sessions', $this->payload(['start_time' => '10:30', 'end_time' => '12:00', 'meeting_no' => 2]))->assertCreated();
        $this->postJson('/api/class-sessions', $this->payload(['start_time' => '07:30', 'end_time' => '09:00', 'meeting_no' => 3]))->assertCreated();
        $this->postJson('/api/class-sessions', $this->payload(['date' => '2026-11-11', 'meeting_no' => 4]))->assertCreated();
        $this->postJson('/api/class-sessions', $this->payload(['trainer_id' => $other->id, 'meeting_no' => 5]))->assertCreated();
    }

    public function test_clash_is_checked_against_every_session_of_the_trainer_including_other_branches_and_school_sessions(): void
    {
        $multi = $this->makeStaff('trainer', 'assigned', [$this->bdg->id, $this->jkt->id]);
        $this->sessionOf($this->classIn($this->jkt), $multi, ['start_time' => '09:00', 'end_time' => '10:00']); // cabang lain, tak terlihat staf Bandung

        $this->postJson('/api/class-sessions', $this->payload(['trainer_id' => $multi->id]))
            ->assertStatus(422)->assertJsonValidationErrors('trainer_id');

        $school = School::create(['name' => 'Sekolah A']);
        $level = Classroom::create(['school_id' => $school->id, 'name' => 'Beginner']);
        ClassSession::create([
            'school_id' => $school->id, 'classroom_id' => $level->id, 'trainer_id' => $this->assigned->id, 'mode' => 'onsite',
            'date' => '2026-11-12', 'start_time' => '13:00', 'end_time' => '14:00', 'meeting_no' => 1,
        ]);
        $this->postJson('/api/class-sessions', $this->payload(['date' => '2026-11-12', 'start_time' => '13:30', 'end_time' => '15:00']))
            ->assertStatus(422)->assertJsonValidationErrors('trainer_id');
    }

    public function test_update_rechecks_clash_but_ignores_the_session_itself(): void
    {
        $first = $this->sessionOf($this->class, $this->assigned);                                                      // 09:00-10:30
        $second = $this->sessionOf($this->class, $this->assigned, ['start_time' => '13:00', 'end_time' => '14:00', 'meeting_no' => 2]);

        // Menimpa waktunya sendiri bukan bentrok.
        $this->putJson("/api/class-sessions/{$first->id}", $this->payload(['note' => 'Ubah catatan']))->assertOk()->assertJsonPath('data.note', 'Ubah catatan');
        // Memindahkan ke jam sesi lain adalah bentrok.
        $this->putJson("/api/class-sessions/{$second->id}", $this->payload(['meeting_no' => 2, 'start_time' => '10:00', 'end_time' => '11:00']))
            ->assertStatus(422)->assertJsonValidationErrors('trainer_id');
        // Mengganti pelaksana menjadi trainer pengganti.
        $substitute = $this->trainer($this->bdg);
        $this->putJson("/api/class-sessions/{$second->id}", $this->payload(['meeting_no' => 2, 'start_time' => '13:00', 'end_time' => '14:00', 'trainer_id' => $substitute->id]))
            ->assertOk()->assertJsonPath('data.is_substitute', true);

        $this->assertSame($substitute->id, $second->fresh()->trainer_id);
    }

    public function test_locked_session_cannot_be_rescheduled_and_class_cannot_be_changed(): void
    {
        $locked = $this->sessionOf($this->class, $this->assigned, ['is_locked' => true]);
        $this->putJson("/api/class-sessions/{$locked->id}", $this->payload(['start_time' => '11:00', 'end_time' => '12:00']))->assertStatus(422);
        $this->assertSame('09:00', substr($locked->fresh()->start_time, 0, 5));

        $open = $this->sessionOf($this->class, $this->assigned, ['date' => '2026-11-20']);
        $other = $this->classIn($this->bdg);
        $this->putJson("/api/class-sessions/{$open->id}", $this->payload(['date' => '2026-11-20', 'class_group_id' => $other->id]))->assertOk();
        $this->assertSame($this->class->id, $open->fresh()->class_group_id);
    }

    public function test_class_or_session_of_another_branch_returns_404(): void
    {
        $otherClass = $this->classIn($this->jkt);
        $otherTrainer = $this->trainer($this->jkt);
        $otherSession = $this->sessionOf($otherClass, $otherTrainer);

        $this->postJson('/api/class-sessions', $this->payload(['class_group_id' => $otherClass->id, 'trainer_id' => $otherTrainer->id]))->assertNotFound();
        $this->getJson("/api/class-sessions/{$otherSession->id}")->assertNotFound();
        $this->putJson("/api/class-sessions/{$otherSession->id}", $this->payload(['class_group_id' => $otherClass->id, 'trainer_id' => $otherTrainer->id, 'note' => 'x']))->assertNotFound();

        $this->assertNull($otherSession->fresh()->note);
        $this->assertSame(1, ClassSession::count());
    }

    public function test_listing_is_scoped_filterable_and_excludes_school_sessions(): void
    {
        $mine = $this->sessionOf($this->class, $this->assigned);
        $later = $this->sessionOf($this->class, $this->assigned, ['date' => '2026-12-01', 'meeting_no' => 2]);
        $other = $this->sessionOf($this->classIn($this->jkt), $this->trainer($this->jkt));
        $school = School::create(['name' => 'Sekolah A']);
        $level = Classroom::create(['school_id' => $school->id, 'name' => 'Beginner']);
        ClassSession::create(['school_id' => $school->id, 'classroom_id' => $level->id, 'trainer_id' => $this->assigned->id, 'mode' => 'onsite', 'date' => '2026-11-10', 'meeting_no' => 1]);

        $this->assertEqualsCanonicalizing([$mine->id, $later->id], $this->getJson('/api/class-sessions')->assertOk()->json('data.*.id'));
        $this->assertEquals([$later->id], $this->getJson('/api/class-sessions?from=2026-11-30&to=2026-12-31')->json('data.*.id'));
        $this->assertEquals([$mine->id, $later->id], $this->getJson("/api/class-sessions?trainer_id={$this->assigned->id}")->json('data.*.id'));
        $this->assertEquals([$mine->id, $later->id], $this->getJson("/api/class-sessions?class_group_id={$this->class->id}")->json('data.*.id'));

        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['manage schedule']));
        $this->assertCount(3, $this->getJson('/api/class-sessions')->json('data'));
    }

    public function test_trainer_sees_assigned_class_sessions_and_sessions_where_substituting_only(): void
    {
        $ownClass = $this->sessionOf($this->class, $this->assigned);
        $otherClass = $this->classIn($this->bdg);
        $substituting = $this->sessionOf($otherClass, $this->assigned, ['date' => '2026-11-11']);   // pengganti di kelas lain
        $stranger = $this->sessionOf($otherClass, $this->trainer($this->bdg), ['date' => '2026-11-12']);

        Sanctum::actingAs($this->assigned);
        $this->assertEqualsCanonicalizing([$ownClass->id, $substituting->id], $this->getJson('/api/class-sessions')->assertOk()->json('data.*.id'));
        $this->getJson("/api/class-sessions/{$substituting->id}")->assertOk();
        $this->getJson("/api/class-sessions/{$stranger->id}")->assertNotFound();
        $this->postJson('/api/class-sessions', $this->payload(['meeting_no' => 9]))->assertForbidden();
        $this->putJson("/api/class-sessions/{$ownClass->id}", $this->payload())->assertForbidden();
    }

    public function test_writing_requires_manage_schedule_and_reading_requires_a_staff_or_trainer_role(): void
    {
        $session = $this->sessionOf($this->class, $this->assigned);

        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage classes']));
        $this->getJson('/api/class-sessions')->assertOk();
        $this->postJson('/api/class-sessions', $this->payload(['meeting_no' => 9]))->assertForbidden();
        $this->putJson("/api/class-sessions/{$session->id}", $this->payload())->assertForbidden();

        Sanctum::actingAs($this->makeStaff('parent'));
        $this->getJson('/api/class-sessions')->assertForbidden();
        $this->getJson("/api/class-sessions/{$session->id}")->assertForbidden();
    }

    public function test_school_session_list_does_not_include_class_group_sessions(): void
    {
        $this->sessionOf($this->class, $this->assigned);
        $school = School::create(['name' => 'Sekolah A']);
        $level = Classroom::create(['school_id' => $school->id, 'name' => 'Beginner']);
        $legacy = ClassSession::create(['school_id' => $school->id, 'classroom_id' => $level->id, 'trainer_id' => $this->assigned->id, 'mode' => 'onsite', 'date' => '2026-11-10', 'meeting_no' => 1]);

        Sanctum::actingAs($this->makeStaff('management', 'all', [], []));
        $this->assertEquals([$legacy->id], $this->getJson('/api/sessions')->assertOk()->json('data.*.id'));
    }

    public function test_management_dashboard_stays_school_based_until_regular_attendance_exists(): void
    {
        // Sesi dan siswa reguler belum punya absensi (Modul 7); jangan tampil sebagai "belum check-in" atau menambah total sekolah.
        $this->sessionOf($this->class, $this->assigned, ['date' => today()->toDateString()]);
        Student::create(['name' => 'Siswa Reguler', 'branch_id' => $this->bdg->id]);

        Sanctum::actingAs($this->makeStaff('management', 'all', [], []));
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('today.sessions', 0)
            ->assertJsonPath('today.trainers_not_checked_in', [])
            ->assertJsonPath('totals.students', 0);
    }

    public function test_schedule_permission_alone_can_read_class_form_options(): void
    {
        $this->getJson('/api/class-groups/options')->assertOk()->assertJsonPath('data.branches.0.id', $this->bdg->id);
        $this->getJson('/api/class-groups')->assertOk();
    }
}
