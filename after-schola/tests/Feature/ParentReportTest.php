<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Lead;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-11; BR-17, AC-12, AC-07): daftar raport anak dan unduh PDF.
 * Ortu hanya melihat raport berstatus terkirim milik anaknya; draft, ditinjau, dan milik ortu lain = 404.
 */
class ParentReportTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    private User $parent;

    private User $trainer;

    private Lead $child;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgramWithLevel();
        $this->program->rubricCriteria()->create(['name' => 'Logika', 'max_score' => 10]);
        $this->trainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $this->parent = $this->makeParent();
        [$this->child, $this->enrollment] = $this->childWithEnrollment($this->parent, 'Budi');
        Sanctum::actingAs($this->parent);
    }

    /** Anak (lead) dengan pendaftaran terverifikasi dan siswa aktif di sebuah kelas. */
    private function childWithEnrollment(User $parent, string $name): array
    {
        $child = $this->makeChild($parent, $this->bdg, $this->program, 'evaluated', $name);
        $enrollment = $this->enrollStudent($this->registrationFor($child, 'terverifikasi'));

        return [$child, $enrollment];
    }

    private function reportFor(Enrollment $enrollment, string $status, string $period = '2026-11'): Evaluation
    {
        $evaluation = Evaluation::create([
            'enrollment_id' => $enrollment->id, 'period' => $period, 'evaluator_id' => $this->trainer->id, 'summary' => 'Berkembang baik.',
        ]);
        $evaluation->criteria()->attach($this->program->rubricCriteria()->first()->id, ['score' => 8]);
        $evaluation->report()->create(['status' => $status, 'sent_at' => $status === 'terkirim' ? now() : null]);

        return $evaluation;
    }

    public function test_parent_sees_only_sent_reports_with_rubric_scores(): void
    {
        $sent = $this->reportFor($this->enrollment, 'terkirim', '2026-11');
        $this->reportFor($this->enrollment, 'ditinjau', '2026-10');
        $this->reportFor($this->enrollment, 'draft', '2026-09');

        $this->getJson("/api/parent/children/{$this->child->id}/reports")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sent->id)
            ->assertJsonPath('data.0.period', '2026-11')
            ->assertJsonPath('data.0.summary', 'Berkembang baik.')
            ->assertJsonPath('data.0.report.status', 'terkirim')
            ->assertJsonPath('data.0.criteria.0.name', 'Logika')
            ->assertJsonPath('data.0.criteria.0.max_score', 10)
            ->assertJsonPath('data.0.criteria.0.score', 8);
    }

    public function test_reports_are_separated_per_child(): void
    {
        [$sibling, $siblingEnrollment] = $this->childWithEnrollment($this->parent, 'Siti');
        $own = $this->reportFor($this->enrollment, 'terkirim');
        $this->reportFor($siblingEnrollment, 'terkirim');

        $this->getJson("/api/parent/children/{$this->child->id}/reports")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_child_without_enrollment_has_an_empty_list(): void
    {
        $newcomer = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'Andi');

        $this->getJson("/api/parent/children/{$newcomer->id}/reports")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_another_parents_child_is_404(): void
    {
        $other = $this->makeParent('Ibu Lain');
        [$otherChild, $otherEnrollment] = $this->childWithEnrollment($other, 'Rudi');
        $this->reportFor($otherEnrollment, 'terkirim');

        $this->getJson("/api/parent/children/{$otherChild->id}/reports")->assertNotFound();
    }

    public function test_pdf_downloads_for_a_sent_report(): void
    {
        $evaluation = $this->reportFor($this->enrollment, 'terkirim');

        $response = $this->get("/api/parent/reports/{$evaluation->id}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('raport-budi-2026-11.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_of_unsent_reports_is_404(): void
    {
        $draft = $this->reportFor($this->enrollment, 'draft', '2026-09');
        $reviewing = $this->reportFor($this->enrollment, 'ditinjau', '2026-10');

        $this->get("/api/parent/reports/{$draft->id}/pdf")->assertNotFound();
        $this->get("/api/parent/reports/{$reviewing->id}/pdf")->assertNotFound();
    }

    public function test_pdf_of_another_parents_report_is_404(): void
    {
        $other = $this->makeParent('Ibu Lain');
        [, $otherEnrollment] = $this->childWithEnrollment($other, 'Rudi');
        $theirs = $this->reportFor($otherEnrollment, 'terkirim');

        $this->get("/api/parent/reports/{$theirs->id}/pdf")->assertNotFound();
    }

    public function test_only_parents_can_use_the_report_endpoints(): void
    {
        $evaluation = $this->reportFor($this->enrollment, 'terkirim');
        Sanctum::actingAs($this->trainer);

        $this->getJson("/api/parent/children/{$this->child->id}/reports")->assertForbidden();
        $this->get("/api/parent/reports/{$evaluation->id}/pdf")->assertForbidden();
    }
}
