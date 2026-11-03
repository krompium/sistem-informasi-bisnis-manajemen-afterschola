<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Program;
use App\Models\Student;
use App\Models\TrainerAssignment;
use App\Models\User;
use App\Notifications\ReportReturnedNotification;
use App\Notifications\ReportSentNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 9b (MGT-50, MGT-51, BR-17, AC-12): koordinator meninjau raport yang diajukan trainer, lalu menyetujui
 * (terkirim, ortu diberi tahu) atau mengembalikan dengan catatan (kembali draft, trainer diberi tahu).
 */
class ReportReviewTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private ClassGroup $class;

    private User $trainer;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->class = $this->classIn($this->bdg);
        $this->trainer = $this->trainerIn($this->bdg, $this->class);
        $this->parent = $this->makeStaff('parent', 'assigned', []);
    }

    private function classIn(Branch $branch): ClassGroup
    {
        $program = Program::create(['name' => 'Coding '.uniqid(), 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $program->rubricCriteria()->create(['name' => 'Logika', 'max_score' => 10]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);

        return ClassGroup::create([
            'program_id' => $program->id, 'level_id' => $level->id, 'branch_id' => $branch->id, 'name' => 'Kelas '.uniqid(), 'capacity' => 10,
        ]);
    }

    private function trainerIn(Branch $branch, ClassGroup $class): User
    {
        $trainer = $this->makeStaff('trainer', 'assigned', [$branch->id]);
        TrainerAssignment::create(['class_group_id' => $class->id, 'trainer_id' => $trainer->id, 'role' => 'utama']);

        return $trainer;
    }

    private function reviewer(Branch $branch): User
    {
        return $this->makeStaff('management', 'assigned', [$branch->id], ['review reports']);
    }

    /** Evaluasi lengkap (skor + ringkasan) dengan raport berstatus $status. Ortu hanya diisi bila diberikan. */
    private function reportOf(string $status, ?ClassGroup $class = null, ?User $evaluator = null, ?User $parent = null, string $name = 'Budi')
    {
        $class ??= $this->class;
        $student = Student::create(['name' => $name, 'branch_id' => $class->branch_id, 'parent_id' => $parent?->id]);
        $enrollment = Enrollment::create(['class_group_id' => $class->id, 'student_id' => $student->id, 'start_date' => '2026-11-01', 'status' => 'aktif']);
        $evaluation = Evaluation::create(['enrollment_id' => $enrollment->id, 'period' => '2026-11', 'evaluator_id' => ($evaluator ?? $this->trainer)->id, 'summary' => 'Berkembang baik.']);
        $evaluation->criteria()->attach($class->program->rubricCriteria()->first()->id, ['score' => 8]);

        return $evaluation->report()->create(['status' => $status]);
    }

    // --- Antrian (MGT-50) ---

    public function test_queue_lists_reports_under_review_for_the_reviewers_branch(): void
    {
        $submitted = $this->reportOf('ditinjau');
        $this->reportOf('draft', name: 'Draft');
        $sent = $this->reportOf('terkirim', name: 'Terkirim');
        $jktClass = $this->classIn($this->jkt);
        $jktSubmitted = $this->reportOf('ditinjau', $jktClass, $this->trainerIn($this->jkt, $jktClass), name: 'Siswa JKT');

        Sanctum::actingAs($this->reviewer($this->bdg));
        $this->getJson('/api/progress-reports')->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submitted->id)
            ->assertJsonPath('data.0.status', 'ditinjau')
            ->assertJsonPath('data.0.evaluation.id', $submitted->evaluation_id)
            ->assertJsonPath('data.0.evaluation.student.name', 'Budi')
            ->assertJsonPath('data.0.evaluation.evaluator.id', $this->trainer->id);
        $this->getJson('/api/progress-reports?status=terkirim')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sent->id);
        $this->getJson('/api/progress-reports?status=draft')->assertJsonCount(1, 'data');

        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['review reports']));
        $this->getJson('/api/progress-reports')->assertJsonCount(2, 'data')->assertJsonFragment(['id' => $jktSubmitted->id]);
    }

    public function test_only_reviewers_can_use_the_queue_and_review_actions(): void
    {
        $report = $this->reportOf('ditinjau');
        $urls = ["/api/progress-reports/{$report->id}/approve", "/api/progress-reports/{$report->id}/return"];

        $this->getJson('/api/progress-reports')->assertUnauthorized();
        $this->postJson($urls[0])->assertUnauthorized();

        foreach ([$this->trainer, $this->parent, $this->makeStaff('finance', 'all'), $this->makeStaff('management', 'all', [], ['manage classes'])] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/progress-reports')->assertForbidden();
            $this->postJson($urls[0])->assertForbidden();
            $this->postJson($urls[1], ['note' => 'x'])->assertForbidden();
        }

        $this->assertSame('ditinjau', $report->fresh()->status);
    }

    // --- Setujui (MGT-51, AC-12) ---

    public function test_approve_publishes_the_report_and_notifies_the_parent(): void
    {
        Notification::fake();
        $report = $this->reportOf('ditinjau', parent: $this->parent);
        $report->update(['review_note' => 'Catatan lama']);
        $reviewer = $this->reviewer($this->bdg);
        $this->assertSame(0, Evaluation::visibleTo($this->parent)->count()); // belum terkirim: tidak terlihat ortu

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/progress-reports/{$report->id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'terkirim')
            ->assertJsonPath('data.reviewer.id', $reviewer->id)
            ->assertJsonPath('data.review_note', null);

        $report->refresh();
        $this->assertSame('terkirim', $report->status);
        $this->assertNotNull($report->sent_at);
        $this->assertSame(1, Evaluation::visibleTo($this->parent)->count());

        Notification::assertSentTo($this->parent, ReportSentNotification::class, fn ($n, $channels) => $channels === ['database', 'mail']
            && $n->toArray($this->parent)['kind'] === 'report_sent' && $n->toArray($this->parent)['evaluation_id'] === $report->evaluation_id);
        Notification::assertSentToTimes($this->parent, ReportSentNotification::class, 1);
        Notification::assertNotSentTo($this->trainer, ReportSentNotification::class);
    }

    public function test_approve_still_succeeds_when_student_has_no_parent_account(): void
    {
        Notification::fake();
        $report = $this->reportOf('ditinjau');

        Sanctum::actingAs($this->reviewer($this->bdg));
        $this->postJson("/api/progress-reports/{$report->id}/approve")->assertOk();

        Notification::assertNothingSent();
    }

    public function test_failed_delivery_does_not_block_approval(): void
    {
        $report = $this->reportOf('ditinjau', parent: $this->parent);
        $this->mock(Dispatcher::class, fn ($mock) => $mock->shouldReceive('send')->andThrow(new \RuntimeException('smtp down')));

        Sanctum::actingAs($this->reviewer($this->bdg));
        $this->postJson("/api/progress-reports/{$report->id}/approve")->assertOk();

        $this->assertSame('terkirim', $report->fresh()->status);
    }

    public function test_only_a_report_under_review_can_be_approved_or_returned(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->reviewer($this->bdg));

        foreach (['draft', 'terkirim'] as $status) {
            $report = $this->reportOf($status, parent: $this->parent, name: "S-$status");

            $this->postJson("/api/progress-reports/{$report->id}/approve")->assertUnprocessable()->assertJsonValidationErrors('status');
            $this->postJson("/api/progress-reports/{$report->id}/return", ['note' => 'Revisi'])->assertUnprocessable()->assertJsonValidationErrors('status');
            $this->assertSame($status, $report->fresh()->status);
        }

        Notification::assertNothingSent();
    }

    // --- Kembalikan (MGT-51) ---

    public function test_return_requires_a_note_and_sends_the_report_back_to_the_trainer(): void
    {
        Notification::fake();
        $report = $this->reportOf('ditinjau', parent: $this->parent);
        $reviewer = $this->reviewer($this->bdg);
        $url = "/api/progress-reports/{$report->id}/return";

        Sanctum::actingAs($reviewer);
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson($url, ['note' => '   '])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson($url, ['note' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->assertSame('ditinjau', $report->fresh()->status);

        $this->postJson($url, ['note' => 'Lengkapi ringkasan.'])->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.review_note', 'Lengkapi ringkasan.')
            ->assertJsonPath('data.reviewer.id', $reviewer->id);

        Notification::assertSentTo($this->trainer, ReportReturnedNotification::class, fn ($n, $channels) => $channels === ['database']
            && $n->toArray($this->trainer)['kind'] === 'report_returned' && str_contains($n->toArray($this->trainer)['message'], 'Budi'));
        Notification::assertNotSentTo($this->parent, ReportReturnedNotification::class);
        Notification::assertNotSentTo($this->parent, ReportSentNotification::class);
    }

    public function test_returned_report_can_be_corrected_and_resubmitted_by_the_trainer(): void
    {
        $report = $this->reportOf('ditinjau');
        Sanctum::actingAs($this->reviewer($this->bdg));
        $this->postJson("/api/progress-reports/{$report->id}/return", ['note' => 'Tambah detail.'])->assertOk();

        Sanctum::actingAs($this->trainer);
        $this->getJson("/api/evaluations/{$report->evaluation_id}")->assertOk()
            ->assertJsonPath('data.report.status', 'draft')->assertJsonPath('data.report.review_note', 'Tambah detail.');
        $this->putJson("/api/evaluations/{$report->evaluation_id}", ['summary' => 'Lebih rinci.'])->assertOk();
        $this->postJson("/api/progress-reports/{$report->id}/submit")->assertOk()->assertJsonPath('data.report.status', 'ditinjau');
    }

    // --- Isolasi cabang ---

    public function test_reviewer_of_another_branch_gets_404_and_nothing_changes(): void
    {
        Notification::fake();
        $report = $this->reportOf('ditinjau', parent: $this->parent);

        Sanctum::actingAs($this->reviewer($this->jkt));
        $this->postJson("/api/progress-reports/{$report->id}/approve")->assertNotFound();
        $this->postJson("/api/progress-reports/{$report->id}/return", ['note' => 'x'])->assertNotFound();
        $this->postJson('/api/progress-reports/999999/approve')->assertNotFound();

        $this->assertSame('ditinjau', $report->fresh()->status);
        Notification::assertNothingSent();
    }
}
