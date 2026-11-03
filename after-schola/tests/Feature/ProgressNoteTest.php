<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\ProgressNote;
use App\Models\Student;
use App\Models\TrainerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 8 (TRN-08, MGT-44, BR-16): catatan progres per siswa per sesi. Hanya trainer kelas tersebut
 * (ditugaskan atau pengganti) yang menulis; staf per cabang dan trainer per penugasan yang membaca.
 * Termasuk roster kelas (TRN-03) yang dipakai halaman catatan dan evaluasi.
 */
class ProgressNoteTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private ClassGroup $class;

    private ClassSession $session;

    private User $trainer;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->class = $this->classIn($this->bdg);
        $this->trainer = $this->trainerIn($this->bdg, $this->class);
        $this->session = $this->sessionOf($this->class, $this->trainer);
        $this->student = $this->enroll($this->class, 'Budi');
    }

    private function classIn(Branch $branch): ClassGroup
    {
        $program = Program::create(['name' => 'Coding '.uniqid(), 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);

        return ClassGroup::create([
            'program_id' => $program->id, 'level_id' => $level->id, 'branch_id' => $branch->id,
            'name' => 'Kelas '.uniqid(), 'capacity' => 10,
        ]);
    }

    /** Trainer cabang; ditugaskan ke $class bila diberikan. */
    private function trainerIn(Branch $branch, ?ClassGroup $class = null, string $role = 'utama'): User
    {
        $trainer = $this->makeStaff('trainer', 'assigned', [$branch->id]);
        if ($class) {
            TrainerAssignment::create(['class_group_id' => $class->id, 'trainer_id' => $trainer->id, 'role' => $role]);
        }

        return $trainer;
    }

    private function sessionOf(ClassGroup $class, User $trainer, array $overrides = []): ClassSession
    {
        return ClassSession::create($overrides + [
            'class_group_id' => $class->id, 'trainer_id' => $trainer->id, 'date' => '2026-11-10',
            'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => 1, 'mode' => 'onsite',
        ]);
    }

    private function enroll(ClassGroup $class, string $name, array $overrides = []): Student
    {
        $student = Student::create(['name' => $name, 'branch_id' => $class->branch_id]);
        Enrollment::create($overrides + [
            'class_group_id' => $class->id, 'student_id' => $student->id, 'start_date' => '2026-11-01', 'status' => 'aktif',
        ]);

        return $student;
    }

    private function noteBy(User $trainer, ?ClassSession $session = null, ?Student $student = null, string $text = 'Catatan'): ProgressNote
    {
        return ProgressNote::create([
            'class_session_id' => ($session ?? $this->session)->id,
            'student_id' => ($student ?? $this->student)->id,
            'trainer_id' => $trainer->id,
            'note' => $text,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'class_session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'note' => 'Paham loop, perlu latihan fungsi.',
        ];
    }

    // --- Tulis (TRN-08, BR-16) ---

    public function test_assigned_trainer_creates_note_with_author_taken_from_login(): void
    {
        $other = $this->trainerIn($this->bdg);
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/progress-notes', $this->payload(['trainer_id' => $other->id]))
            ->assertCreated()
            ->assertJsonPath('data.student.id', $this->student->id)
            ->assertJsonPath('data.class_session.id', $this->session->id)
            ->assertJsonPath('data.trainer.id', $this->trainer->id)
            ->assertJsonPath('data.note', 'Paham loop, perlu latihan fungsi.');

        $this->assertDatabaseHas('progress_notes', ['student_id' => $this->student->id, 'trainer_id' => $this->trainer->id]);
    }

    public function test_substitute_trainer_without_assignment_can_write_for_the_session_they_run(): void
    {
        $substitute = $this->trainerIn($this->bdg);
        $session = $this->sessionOf($this->class, $substitute, ['date' => '2026-11-12', 'meeting_no' => 2]);
        Sanctum::actingAs($substitute);

        $this->postJson('/api/progress-notes', $this->payload(['class_session_id' => $session->id]))->assertCreated();
    }

    public function test_trainer_of_another_class_gets_404_for_this_session(): void
    {
        $otherClass = $this->classIn($this->bdg);
        Sanctum::actingAs($this->trainerIn($this->bdg, $otherClass));

        $this->postJson('/api/progress-notes', $this->payload())->assertNotFound();
        $this->postJson('/api/progress-notes', $this->payload(['class_session_id' => 999999]))->assertNotFound(); // tidak membocorkan ID yang ada
        $this->assertDatabaseCount('progress_notes', 0);
    }

    public function test_school_session_without_class_group_is_404(): void
    {
        $schoolSession = ClassSession::create([
            'trainer_id' => $this->trainer->id, 'date' => '2026-11-10', 'start_time' => '13:00', 'end_time' => '14:00',
            'meeting_no' => 1, 'mode' => 'onsite',
        ]);
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/progress-notes', $this->payload(['class_session_id' => $schoolSession->id]))->assertNotFound();
    }

    public function test_student_must_be_enrolled_in_the_class_on_the_session_date(): void
    {
        Sanctum::actingAs($this->trainer);
        $notEnrolled = Student::create(['name' => 'Tanpa Kelas', 'branch_id' => $this->bdg->id]);
        $startsLater = $this->enroll($this->class, 'Mulai Nanti', ['start_date' => '2026-11-20']);
        $alreadyEnded = $this->enroll($this->class, 'Sudah Selesai', ['end_date' => '2026-11-05', 'status' => 'selesai']);
        $otherClassStudent = $this->enroll($this->classIn($this->bdg), 'Kelas Lain');

        foreach ([$notEnrolled, $startsLater, $alreadyEnded, $otherClassStudent] as $student) {
            $this->postJson('/api/progress-notes', $this->payload(['student_id' => $student->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        }
        $this->assertDatabaseCount('progress_notes', 0);
    }

    public function test_note_is_required_and_limited(): void
    {
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/progress-notes', $this->payload(['note' => '']))->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson('/api/progress-notes', $this->payload(['note' => str_repeat('a', 2001)]))->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson('/api/progress-notes', ['note' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['class_session_id', 'student_id']);
    }

    public function test_staff_and_parent_cannot_write_or_read(): void
    {
        $this->noteBy($this->trainer);
        $coordinator = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage classes']);
        $parent = $this->makeStaff('parent', 'assigned', []);
        $finance = $this->makeStaff('finance', 'all');

        Sanctum::actingAs($coordinator);
        $this->postJson('/api/progress-notes', $this->payload())->assertForbidden();

        foreach ([$parent, $finance] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/progress-notes', $this->payload())->assertForbidden();
            $this->getJson('/api/progress-notes')->assertForbidden();
        }
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson('/api/progress-notes', $this->payload())->assertUnauthorized();
        $this->getJson('/api/class-groups/'.$this->class->id.'/enrollments')->assertUnauthorized();
    }

    // --- Ubah: hanya penulis ---

    public function test_author_can_update_own_note(): void
    {
        $note = $this->noteBy($this->trainer, text: 'Awal');
        Sanctum::actingAs($this->trainer);

        $this->putJson("/api/progress-notes/{$note->id}", ['note' => 'Revisi'])->assertOk()->assertJsonPath('data.note', 'Revisi');
        $this->assertDatabaseHas('progress_notes', ['id' => $note->id, 'note' => 'Revisi']);
    }

    public function test_other_trainer_of_same_class_cannot_update_but_outsider_gets_404(): void
    {
        $note = $this->noteBy($this->trainer, text: 'Awal');

        Sanctum::actingAs($this->trainerIn($this->bdg, $this->class, 'asisten'));
        $this->putJson("/api/progress-notes/{$note->id}", ['note' => 'Ditimpa'])->assertForbidden();

        Sanctum::actingAs($this->trainerIn($this->bdg, $this->classIn($this->bdg)));
        $this->putJson("/api/progress-notes/{$note->id}", ['note' => 'Ditimpa'])->assertNotFound();

        $this->assertDatabaseHas('progress_notes', ['id' => $note->id, 'note' => 'Awal']);
    }

    // --- Baca (MGT-44) ---

    public function test_trainer_lists_notes_of_own_classes_only_and_filters_work(): void
    {
        $assistant = $this->trainerIn($this->bdg, $this->class, 'asisten');
        $second = $this->enroll($this->class, 'Sari');
        $mine = $this->noteBy($this->trainer, text: 'Milik Budi');
        $fromAssistant = $this->noteBy($assistant, student: $second, text: 'Milik Sari');

        $otherClass = $this->classIn($this->bdg);
        $otherSession = $this->sessionOf($otherClass, $this->trainerIn($this->bdg, $otherClass));
        $foreign = $this->noteBy($otherSession->trainer, $otherSession, $this->enroll($otherClass, 'Asing'), 'Kelas lain');

        Sanctum::actingAs($this->trainer);
        $this->getJson('/api/progress-notes')->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $mine->id])
            ->assertJsonFragment(['id' => $fromAssistant->id])
            ->assertJsonMissing(['id' => $foreign->id]);

        $this->getJson('/api/progress-notes?student_id='.$second->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $fromAssistant->id);
        $this->getJson('/api/progress-notes?class_session_id='.$otherSession->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_reads_notes_of_own_branch_only(): void
    {
        $note = $this->noteBy($this->trainer);
        $jktClass = $this->classIn($this->jkt);
        $jktTrainer = $this->trainerIn($this->jkt, $jktClass);
        $jktNote = $this->noteBy($jktTrainer, $this->sessionOf($jktClass, $jktTrainer), $this->enroll($jktClass, 'Siswa JKT'));

        foreach (['manage classes', 'assign trainers', 'manage schedule', 'review reports'] as $permission) {
            Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->bdg->id], [$permission]));
            $this->getJson('/api/progress-notes')->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $note->id)
                ->assertJsonMissing(['id' => $jktNote->id]);
        }

        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['manage classes']));
        $this->getJson('/api/progress-notes')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_management_without_class_permissions_sees_nothing(): void
    {
        $this->noteBy($this->trainer);
        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['view dashboard']));

        $this->getJson('/api/progress-notes')->assertOk()->assertJsonCount(0, 'data');
    }

    // --- Roster kelas (TRN-03) ---

    public function test_assigned_trainer_and_branch_staff_see_active_roster_by_default(): void
    {
        $this->enroll($this->class, 'Sudah Selesai', ['status' => 'selesai', 'end_date' => '2026-11-05']);

        foreach ([$this->trainer, $this->makeStaff('management', 'assigned', [$this->bdg->id], ['manage classes'])] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/class-groups/{$this->class->id}/enrollments")->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.student.id', $this->student->id)
                ->assertJsonPath('data.0.status', 'aktif');
            $this->getJson("/api/class-groups/{$this->class->id}/enrollments?status=selesai")->assertOk()->assertJsonCount(1, 'data');
        }
    }

    public function test_roster_of_a_class_outside_scope_is_404(): void
    {
        Sanctum::actingAs($this->trainerIn($this->bdg, $this->classIn($this->bdg)));
        $this->getJson("/api/class-groups/{$this->class->id}/enrollments")->assertNotFound();

        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->jkt->id], ['manage classes']));
        $this->getJson("/api/class-groups/{$this->class->id}/enrollments")->assertNotFound();
    }
}
