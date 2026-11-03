<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Program;
use App\Models\RubricCriterion;
use App\Models\Student;
use App\Models\TrainerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 9a (TRN-09, TRN-10, BR-16): evaluasi periodik per enrollment dengan skor per kriteria rubrik program.
 * Report berstatus draft dibuat bersamaan; trainer menyimpan draft lalu mengajukan tinjau (draft -> ditinjau).
 */
class EvaluationTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    private RubricCriterion $logic;

    private RubricCriterion $creativity;

    private ClassGroup $class;

    private User $trainer;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->programWithRubric();
        $this->logic = $this->program->rubricCriteria()->first();
        $this->creativity = $this->program->rubricCriteria()->skip(1)->first();
        $this->class = $this->classIn($this->bdg, $this->program);
        $this->trainer = $this->trainerIn($this->bdg, $this->class);
        $this->enrollment = $this->enroll($this->class, 'Budi');
    }

    private function programWithRubric(bool $withRubric = true): Program
    {
        $program = Program::create(['name' => 'Coding '.uniqid(), 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);
        if ($withRubric) {
            $program->rubricCriteria()->create(['name' => 'Logika', 'max_score' => 10]);
            $program->rubricCriteria()->create(['name' => 'Kreativitas', 'max_score' => 5]);
        }

        return $program;
    }

    private function classIn(Branch $branch, ?Program $program = null): ClassGroup
    {
        $program ??= $this->programWithRubric();

        return ClassGroup::create([
            'program_id' => $program->id, 'level_id' => $program->levels()->first()->id, 'branch_id' => $branch->id,
            'name' => 'Kelas '.uniqid(), 'capacity' => 10,
        ]);
    }

    private function trainerIn(Branch $branch, ?ClassGroup $class = null, string $role = 'utama'): User
    {
        $trainer = $this->makeStaff('trainer', 'assigned', [$branch->id]);
        if ($class) {
            TrainerAssignment::create(['class_group_id' => $class->id, 'trainer_id' => $trainer->id, 'role' => $role]);
        }

        return $trainer;
    }

    private function enroll(ClassGroup $class, string $name, ?User $parent = null): Enrollment
    {
        $student = Student::create(['name' => $name, 'branch_id' => $class->branch_id, 'parent_id' => $parent?->id]);

        return Enrollment::create(['class_group_id' => $class->id, 'student_id' => $student->id, 'start_date' => '2026-11-01', 'status' => 'aktif']);
    }

    /** Evaluasi + report draft langsung lewat model (tanpa API). */
    private function evaluationBy(User $trainer, ?Enrollment $enrollment = null, string $period = '2026-11', string $reportStatus = 'draft'): Evaluation
    {
        $evaluation = Evaluation::create([
            'enrollment_id' => ($enrollment ?? $this->enrollment)->id, 'period' => $period, 'evaluator_id' => $trainer->id,
        ]);
        $evaluation->report()->create(['status' => $reportStatus]);

        return $evaluation;
    }

    private function scoreAll(Evaluation $evaluation, int $logic = 8, int $creativity = 4): void
    {
        $evaluation->criteria()->attach([$this->logic->id => ['score' => $logic], $this->creativity->id => ['score' => $creativity]]);
    }

    private function complete(Evaluation $evaluation): Evaluation
    {
        $this->scoreAll($evaluation);
        $evaluation->update(['summary' => 'Berkembang baik.']);

        return $evaluation;
    }

    // --- Buat (TRN-09) ---

    public function test_assigned_trainers_create_evaluation_with_draft_report(): void
    {
        $assistant = $this->trainerIn($this->bdg, $this->class, 'asisten');
        $other = $this->trainerIn($this->bdg);
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/evaluations', ['enrollment_id' => $this->enrollment->id, 'period' => '2026-11', 'evaluator_id' => $other->id])
            ->assertCreated()
            ->assertJsonPath('data.period', '2026-11')
            ->assertJsonPath('data.evaluator.id', $this->trainer->id)
            ->assertJsonPath('data.enrollment.student.name', 'Budi')
            ->assertJsonPath('data.report.status', 'draft')
            ->assertJsonCount(2, 'data.criteria')
            ->assertJsonPath('data.criteria.0.name', 'Logika')
            ->assertJsonPath('data.criteria.0.max_score', 10)
            ->assertJsonPath('data.criteria.0.score', null);

        $this->assertDatabaseHas('progress_reports', ['status' => 'draft']);

        Sanctum::actingAs($assistant);
        $this->postJson('/api/evaluations', ['enrollment_id' => $this->enrollment->id, 'period' => '2026-12'])->assertCreated();
    }

    public function test_outsiders_cannot_create(): void
    {
        $payload = ['enrollment_id' => $this->enrollment->id, 'period' => '2026-11'];

        Sanctum::actingAs($this->trainerIn($this->bdg, $this->classIn($this->bdg)));
        $this->postJson('/api/evaluations', $payload)->assertNotFound();
        $this->postJson('/api/evaluations', ['enrollment_id' => 999999, 'period' => '2026-11'])->assertNotFound();

        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['manage classes', 'review reports']));
        $this->postJson('/api/evaluations', $payload)->assertForbidden();

        Sanctum::actingAs($this->makeStaff('parent', 'assigned', []));
        $this->postJson('/api/evaluations', $payload)->assertForbidden();

        $this->assertDatabaseCount('evaluations', 0);
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson('/api/evaluations', [])->assertUnauthorized();
        $this->getJson('/api/evaluations')->assertUnauthorized();
        $this->postJson('/api/progress-reports/1/submit')->assertUnauthorized();
    }

    public function test_period_is_required_and_unique_per_enrollment(): void
    {
        $this->evaluationBy($this->trainer, period: '2026-11');
        $second = $this->trainerIn($this->bdg, $this->class, 'asisten');
        $otherStudent = $this->enroll($this->class, 'Sari');

        Sanctum::actingAs($second);
        $this->postJson('/api/evaluations', ['enrollment_id' => $this->enrollment->id, 'period' => ''])->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->postJson('/api/evaluations', ['enrollment_id' => $this->enrollment->id, 'period' => '2026-11'])->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->postJson('/api/evaluations', ['enrollment_id' => $otherStudent->id, 'period' => '2026-11'])->assertCreated();
        $this->assertDatabaseCount('evaluations', 2);
    }

    // --- Simpan draft ---

    public function test_draft_can_be_saved_partially_and_updated_without_duplicating_scores(): void
    {
        $evaluation = $this->evaluationBy($this->trainer);
        Sanctum::actingAs($this->trainer);

        $this->putJson("/api/evaluations/{$evaluation->id}", ['summary' => 'Awal', 'scores' => [['criterion_id' => $this->logic->id, 'score' => 8]]])
            ->assertOk()
            ->assertJsonPath('data.summary', 'Awal')
            ->assertJsonPath('data.criteria.0.score', 8)
            ->assertJsonPath('data.criteria.1.score', null);
        $this->assertDatabaseCount('evaluation_scores', 1);

        $this->putJson("/api/evaluations/{$evaluation->id}", ['scores' => [
            ['criterion_id' => $this->logic->id, 'score' => 9],
            ['criterion_id' => $this->creativity->id, 'score' => 4],
        ]])->assertOk()->assertJsonPath('data.criteria.0.score', 9)->assertJsonPath('data.criteria.1.score', 4)->assertJsonPath('data.summary', 'Awal');
        $this->assertDatabaseCount('evaluation_scores', 2);
    }

    public function test_scores_are_validated_against_the_programs_rubric(): void
    {
        $evaluation = $this->evaluationBy($this->trainer);
        $foreignCriterion = $this->programWithRubric()->rubricCriteria()->first();
        Sanctum::actingAs($this->trainer);
        $url = "/api/evaluations/{$evaluation->id}";

        $this->putJson($url, ['scores' => [['criterion_id' => $this->creativity->id, 'score' => 6]]])->assertUnprocessable()->assertJsonValidationErrors('scores.0.score');
        $this->putJson($url, ['scores' => [['criterion_id' => $this->logic->id, 'score' => -1]]])->assertUnprocessable()->assertJsonValidationErrors('scores.0.score');
        $this->putJson($url, ['scores' => [['criterion_id' => $this->logic->id, 'score' => 'tinggi']]])->assertUnprocessable()->assertJsonValidationErrors('scores.0.score');
        $this->putJson($url, ['scores' => [['criterion_id' => $foreignCriterion->id, 'score' => 3]]])->assertUnprocessable()->assertJsonValidationErrors('scores.0.criterion_id');
        $this->putJson($url, ['scores' => [
            ['criterion_id' => $this->logic->id, 'score' => 5], ['criterion_id' => $this->logic->id, 'score' => 6],
        ]])->assertUnprocessable()->assertJsonValidationErrors('scores.0.criterion_id');
        $this->assertDatabaseCount('evaluation_scores', 0);
    }

    // --- Ajukan tinjau ---

    public function test_submit_requires_all_criteria_scored_and_a_summary(): void
    {
        $evaluation = $this->evaluationBy($this->trainer);
        $url = "/api/progress-reports/{$evaluation->report->id}/submit";
        Sanctum::actingAs($this->trainer);

        $this->postJson($url)->assertUnprocessable();

        $evaluation->criteria()->attach($this->logic->id, ['score' => 8]); // kreativitas belum
        $evaluation->update(['summary' => 'Ringkasan']);
        $this->postJson($url)->assertUnprocessable();

        $evaluation->criteria()->attach($this->creativity->id, ['score' => 4]);
        $evaluation->update(['summary' => '  ']);
        $this->postJson($url)->assertUnprocessable();

        $this->assertSame('draft', $evaluation->report->fresh()->status);
    }

    public function test_submit_fails_when_program_has_no_rubric(): void
    {
        $class = $this->classIn($this->bdg, $this->programWithRubric(false));
        $trainer = $this->trainerIn($this->bdg, $class);
        $evaluation = $this->evaluationBy($trainer, $this->enroll($class, 'Tanpa Rubrik'));
        $evaluation->update(['summary' => 'Ringkasan']);
        Sanctum::actingAs($trainer);

        $this->postJson("/api/progress-reports/{$evaluation->report->id}/submit")->assertUnprocessable();
    }

    public function test_complete_evaluation_is_submitted_for_review(): void
    {
        $evaluation = $this->complete($this->evaluationBy($this->trainer));
        Sanctum::actingAs($this->trainer);

        $this->postJson("/api/progress-reports/{$evaluation->report->id}/submit")
            ->assertOk()->assertJsonPath('data.report.status', 'ditinjau')->assertJsonPath('data.id', $evaluation->id);
    }

    public function test_evaluation_is_locked_once_submitted_or_sent(): void
    {
        foreach (['ditinjau', 'terkirim'] as $status) {
            $evaluation = $this->complete($this->evaluationBy($this->trainer, period: "p-$status", reportStatus: $status));
            Sanctum::actingAs($this->trainer);

            $this->putJson("/api/evaluations/{$evaluation->id}", ['summary' => 'Diubah'])->assertUnprocessable();
            $this->postJson("/api/progress-reports/{$evaluation->report->id}/submit")->assertUnprocessable();
            $this->assertSame('Berkembang baik.', $evaluation->fresh()->summary);
        }
    }

    // --- Isolasi (PRD §9) ---

    public function test_draft_is_visible_to_its_author_only_among_trainers_and_to_branch_reviewers(): void
    {
        $evaluation = $this->complete($this->evaluationBy($this->trainer));
        $submitUrl = "/api/progress-reports/{$evaluation->report->id}/submit";

        foreach ([$this->trainerIn($this->bdg, $this->class, 'asisten'), $this->trainerIn($this->bdg, $this->classIn($this->bdg))] as $outsider) {
            Sanctum::actingAs($outsider);
            $this->getJson("/api/evaluations/{$evaluation->id}")->assertNotFound();
            $this->putJson("/api/evaluations/{$evaluation->id}", ['summary' => 'x'])->assertNotFound();
            $this->postJson($submitUrl)->assertNotFound();
        }

        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->jkt->id], ['review reports']));
        $this->getJson("/api/evaluations/{$evaluation->id}")->assertNotFound();

        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->bdg->id], ['review reports']));
        $this->getJson("/api/evaluations/{$evaluation->id}")->assertOk()->assertJsonPath('data.report.status', 'draft');
        $this->putJson("/api/evaluations/{$evaluation->id}", ['summary' => 'x'])->assertForbidden();
        $this->postJson($submitUrl)->assertForbidden();

        $this->assertSame('draft', $evaluation->report->fresh()->status);
    }

    public function test_index_is_scoped_per_role_and_filterable(): void
    {
        $mine = $this->evaluationBy($this->trainer, period: '2026-11');
        $sari = $this->enroll($this->class, 'Sari');
        $submitted = $this->evaluationBy($this->trainer, $sari, '2026-11', 'ditinjau');

        $otherClass = $this->classIn($this->bdg);
        $other = $this->trainerIn($this->bdg, $otherClass);
        $foreign = $this->evaluationBy($other, $this->enroll($otherClass, 'Asing'));

        $jktClass = $this->classIn($this->jkt);
        $jktEvaluation = $this->evaluationBy($this->trainerIn($this->jkt, $jktClass), $this->enroll($jktClass, 'Siswa JKT'));

        Sanctum::actingAs($this->trainer);
        $this->getJson('/api/evaluations')->assertOk()->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data')->assertJsonMissing(['id' => $foreign->id]);
        $this->getJson('/api/evaluations?status=ditinjau')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $submitted->id);
        $this->getJson('/api/evaluations?enrollment_id='.$this->enrollment->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/evaluations?class_group_id='.$otherClass->id)->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->bdg->id], ['review reports']));
        $this->getJson('/api/evaluations')->assertOk()->assertJsonCount(3, 'data')->assertJsonMissing(['id' => $jktEvaluation->id]);

        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['manage classes'])); // tanpa review reports
        $this->getJson('/api/evaluations')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->makeStaff('parent', 'assigned', []));
        $this->getJson('/api/evaluations')->assertForbidden();
    }

    public function test_scope_for_parents_shows_only_sent_reports_of_their_own_child(): void
    {
        $parent = $this->makeStaff('parent', 'assigned', []);
        $otherParent = $this->makeStaff('parent', 'assigned', []);
        $enrollment = $this->enroll($this->class, 'Anak Ortu', $parent);
        $evaluation = $this->evaluationBy($this->trainer, $enrollment);

        $this->assertSame(0, Evaluation::visibleTo($parent)->count()); // draft: tidak terlihat

        $evaluation->report->update(['status' => 'ditinjau']);
        $this->assertSame(0, Evaluation::visibleTo($parent)->count());

        $evaluation->report->update(['status' => 'terkirim']);
        $this->assertSame(1, Evaluation::visibleTo($parent)->count());
        $this->assertSame(0, Evaluation::visibleTo($otherParent)->count());
    }

    // --- Kriteria rubrik yang sudah dipakai (FK restrict) ---

    public function test_criterion_used_by_an_evaluation_cannot_be_deleted(): void
    {
        $this->scoreAll($this->evaluationBy($this->trainer));
        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['manage programs']));
        $base = "/api/programs/{$this->program->id}/rubric-criteria";

        $this->deleteJson("$base/{$this->logic->id}")->assertUnprocessable();
        $this->assertDatabaseHas('rubric_criteria', ['id' => $this->logic->id]);

        $unused = $this->program->rubricCriteria()->create(['name' => 'Baru', 'max_score' => 5]);
        $this->deleteJson("$base/{$unused->id}")->assertNoContent();
    }
}
