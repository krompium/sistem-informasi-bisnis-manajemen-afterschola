<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\EnrollmentDecision;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-04, ORT-05): hasil trial dan penawaran program, lalu keputusan Lanjut atau Tidak lanjut.
 * Keputusan hanya dibuat orang tua (BR-02); tidak lanjut menjadikan Leads tanpa menghapus data (BR-03).
 */
class ParentDecisionTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgramWithLevel('Coding Kids', 750000);
        $this->parent = $this->makeParent();
        Sanctum::actingAs($this->parent);
    }

    private function decide(Lead $child, string $decision, array $extra = [])
    {
        return $this->postJson("/api/parent/children/{$child->id}/decision", ['decision' => $decision] + $extra);
    }

    public function test_trial_result_shows_scores_recommended_level_and_program_offer(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);

        $this->getJson("/api/parent/children/{$child->id}")->assertOk()
            ->assertJsonPath('data.evaluation.attendance', 'hadir')
            ->assertJsonPath('data.evaluation.scores.enthusiasm', 4)
            ->assertJsonPath('data.evaluation.scores.understanding', 5)
            ->assertJsonPath('data.evaluation.strengths', 'Cepat memahami logika')
            ->assertJsonPath('data.evaluation.improvements', 'Fokus perlu dilatih')
            ->assertJsonPath('data.evaluation.recommended_level', 'Dasar')
            ->assertJsonPath('data.program.name', 'Coding Kids')
            ->assertJsonPath('data.program.price', 750000)
            ->assertJsonPath('data.decision', null);
    }

    public function test_trial_result_is_empty_until_the_trial_is_evaluated(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program, 'scheduled');

        $this->getJson("/api/parent/children/{$child->id}")->assertOk()->assertJsonPath('data.evaluation', null);
        $this->decide($child, 'lanjut')->assertStatus(422)->assertJsonValidationErrors('decision');
    }

    public function test_children_of_other_parents_return_404_everywhere(): void
    {
        $other = $this->makeChild($this->makeParent('Ibu Lain'), $this->bdg, $this->program);

        $this->getJson("/api/parent/children/{$other->id}")->assertNotFound();
        $this->decide($other, 'lanjut')->assertNotFound();
        $this->assertSame(0, EnrollmentDecision::count());
    }

    public function test_parent_decides_to_continue_and_it_is_recorded_under_their_name(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);

        $this->decide($child, 'lanjut')->assertOk()
            ->assertJsonPath('data.decision.decision', 'lanjut')
            ->assertJsonPath('data.stage', 'siap_daftar')
            ->assertJsonPath('data.next_action', 'daftar');

        $decision = EnrollmentDecision::sole();
        $this->assertSame($this->parent->id, $decision->decided_by);
        $this->assertSame('trial_scheduled', $child->fresh()->status);
    }

    public function test_declining_turns_the_child_into_leads_and_keeps_the_data(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);

        $this->decide($child, 'tidak', ['reason' => 'Jadwal bentrok'])->assertOk()
            ->assertJsonPath('data.stage', 'tidak_lanjut');

        $this->assertSame('not_continued', $child->fresh()->status);
        $this->assertSame('Jadwal bentrok', EnrollmentDecision::sole()->reason);
        $this->assertNotNull($child->fresh()->trialEvaluation);
    }

    public function test_decision_can_be_changed_before_registration_is_submitted(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $this->decide($child, 'tidak')->assertOk();

        $this->decide($child, 'lanjut')->assertOk()->assertJsonPath('data.stage', 'siap_daftar');

        $this->assertSame('trial_scheduled', $child->fresh()->status);
        $this->assertSame(1, EnrollmentDecision::count());
    }

    public function test_declining_discards_a_registration_draft(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $this->registrationFor($child, 'draft');

        $this->decide($child, 'tidak')->assertOk();

        $this->assertSame(0, Registration::count());
    }

    public function test_decision_cannot_be_withdrawn_once_registration_is_submitted(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $this->registrationFor($child, 'menunggu_verifikasi');

        $this->decide($child, 'tidak')->assertStatus(422)->assertJsonValidationErrors('decision');

        $this->assertSame('lanjut', EnrollmentDecision::sole()->decision);
        $this->assertSame(1, Registration::count());
    }

    public function test_child_who_missed_the_trial_cannot_be_decided(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program, 'absent');

        $this->decide($child, 'lanjut')->assertStatus(422)->assertJsonValidationErrors('decision');
    }

    public function test_parents_only_choose_between_continue_and_decline(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);

        $this->decide($child, 'pikir_pikir', ['follow_up_date' => today()->addWeek()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('decision');
        $this->postJson("/api/parent/children/{$child->id}/decision", [])->assertStatus(422);
    }

    public function test_staff_cannot_use_the_parent_endpoints(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        Sanctum::actingAs($this->makeStaff('management', 'all'));

        $this->getJson("/api/parent/children/{$child->id}")->assertForbidden();
        $this->decide($child, 'lanjut')->assertForbidden();
    }
}
