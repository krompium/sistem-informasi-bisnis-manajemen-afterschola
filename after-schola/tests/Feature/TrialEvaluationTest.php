<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\TrialEvaluation;
use App\Models\TrialSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 3 (Fase 2): evaluasi trial per peserta slot (TRN-04, TRN-05, BR-19).
 * Trainer hanya slot miliknya, staf per cabang, ortu tidak punya akses. Di luar scope: 404 (show/update/delete)
 * atau 422 pada lead_id (store: FK dari body divalidasi di Form Request, PRD bagian 9).
 */
class TrialEvaluationTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    private ProgramLevel $level;

    private User $trainer;

    private User $otherTrainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgram('Coding Kids');
        $this->level = $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
        $this->trainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $this->otherTrainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
    }

    private function makeProgram(string $name): Program
    {
        return Program::create(['name' => $name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
    }

    private function makeSlot(Branch $branch, User $trainer, ?Program $program = null): TrialSlot
    {
        return TrialSlot::create([
            'branch_id' => $branch->id,
            'program_id' => ($program ?? $this->program)->id,
            'trainer_id' => $trainer->id,
            'starts_at' => now()->subDay(),
            'capacity' => 10,
        ]);
    }

    private function makeLead(TrialSlot $slot, string $name = 'Budi'): Lead
    {
        $parent = $this->makeStaff('parent');

        return Lead::create([
            'name' => $name,
            'education_level' => 'sd',
            'origin_school' => 'SD Negeri 1',
            'parent_email' => $parent->email,
            'parent_id' => $parent->id,
            'branch_id' => $slot->branch_id,
            'program_id' => $slot->program_id,
            'trial_slot_id' => $slot->id,
            'status' => 'trial_scheduled',
        ]);
    }

    private function payload(Lead $lead, array $overrides = []): array
    {
        return array_merge([
            'lead_id' => $lead->id,
            'attendance' => 'hadir',
            'score_enthusiasm' => 4,
            'score_understanding' => 4,
            'score_focus' => 3,
            'score_collaboration' => 5,
            'strengths' => 'Cepat paham',
            'improvements' => 'Fokus',
            'recommended_level_id' => $this->level->id,
            'recommendation' => 'lanjut',
        ], $overrides);
    }

    private function makeEvaluation(Lead $lead, array $overrides = []): TrialEvaluation
    {
        $slot = $lead->trialSlot;

        return TrialEvaluation::create(array_merge([
            'lead_id' => $lead->id,
            'trial_slot_id' => $slot->id,
            'branch_id' => $slot->branch_id,
            'program_id' => $slot->program_id,
            'participant_name' => $lead->name,
            'trainer_id' => $slot->trainer_id,
            'trial_date' => '2026-10-30',
            'attendance' => 'hadir',
            'score_enthusiasm' => 4, 'score_understanding' => 4, 'score_focus' => 4, 'score_collaboration' => 4,
            'recommendation' => 'lanjut',
        ], $overrides));
    }

    private function staff(string $scope = 'all', array $branchIds = []): User
    {
        return $this->makeStaff('management', $scope, $branchIds);
    }

    // ---------- TRN-04: slot dan peserta saya ----------

    public function test_trainer_lists_only_own_slots_with_participants_and_program_levels(): void
    {
        $own = $this->makeSlot($this->bdg, $this->trainer);
        $lead = $this->makeLead($own, 'Budi');
        $this->makeLead($this->makeSlot($this->bdg, $this->otherTrainer), 'Siti');
        Sanctum::actingAs($this->trainer);

        $res = $this->getJson('/api/trainer/trials')->assertOk();

        $res->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.program.levels.0.id', $this->level->id)
            ->assertJsonCount(1, 'data.0.participants')
            ->assertJsonPath('data.0.participants.0.lead_id', $lead->id)
            ->assertJsonPath('data.0.participants.0.name', 'Budi')
            ->assertJsonPath('data.0.participants.0.evaluation_id', null);
    }

    public function test_trainer_trials_shows_evaluation_status_and_supports_period_filter(): void
    {
        $past = $this->makeSlot($this->bdg, $this->trainer);
        $evaluation = $this->makeEvaluation($this->makeLead($past));
        $upcoming = TrialSlot::create([
            'branch_id' => $this->bdg->id, 'program_id' => $this->program->id, 'trainer_id' => $this->trainer->id,
            'starts_at' => now()->addDays(2), 'capacity' => 5,
        ]);
        Sanctum::actingAs($this->trainer);

        $this->getJson('/api/trainer/trials?period=past')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.participants.0.evaluation_id', $evaluation->id)
            ->assertJsonPath('data.0.participants.0.attendance', 'hadir');
        $this->getJson('/api/trainer/trials?period=upcoming')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $upcoming->id);
    }

    public function test_staff_trial_list_is_limited_to_own_branches(): void
    {
        $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        $jktTrainer = $this->makeStaff('trainer', 'assigned', [$this->jkt->id]);
        $this->makeLead($this->makeSlot($this->jkt, $jktTrainer));

        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));
        $this->getJson('/api/trainer/trials')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($this->staff('all'));
        $this->getJson('/api/trainer/trials')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_parent_and_guest_cannot_access_trial_list_or_evaluations(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        $evaluation = $this->makeEvaluation($lead);

        $this->getJson('/api/trainer/trials')->assertStatus(401);

        Sanctum::actingAs($this->makeStaff('parent'));
        $this->getJson('/api/trainer/trials')->assertStatus(403);
        $this->getJson('/api/trial-evaluations')->assertStatus(403);
        $this->getJson("/api/trial-evaluations/{$evaluation->id}")->assertStatus(403);
        $this->postJson('/api/trial-evaluations', $this->payload($lead))->assertStatus(403);
    }

    // ---------- TRN-05: membuat evaluasi ----------

    public function test_trainer_evaluates_participant_of_own_slot(): void
    {
        $slot = $this->makeSlot($this->bdg, $this->trainer);
        $lead = $this->makeLead($slot, 'Budi');
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', $this->payload($lead))
            ->assertCreated()
            ->assertJsonPath('data.lead_id', $lead->id)
            ->assertJsonPath('data.participant_name', 'Budi')
            ->assertJsonPath('data.recommended_level_id', $this->level->id)
            ->assertJsonPath('data.recommended_level', 'Dasar')
            ->assertJsonPath('data.average_score', 4);

        $this->assertDatabaseHas('trial_evaluations', [
            'lead_id' => $lead->id,
            'trial_slot_id' => $slot->id,
            'branch_id' => $this->bdg->id,
            'program_id' => $this->program->id,
            'trainer_id' => $this->trainer->id,
            'participant_name' => 'Budi',
            'origin_school' => 'SD Negeri 1',
        ]);
    }

    public function test_identity_and_trial_date_come_from_the_lead_and_slot_not_the_client(): void
    {
        $slot = $this->makeSlot($this->bdg, $this->trainer);
        $lead = $this->makeLead($slot, 'Budi');
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', $this->payload($lead, [
            'participant_name' => 'Nama Palsu', 'trial_date' => '2001-01-01', 'trainer_id' => 999, 'branch_id' => $this->jkt->id,
        ]))->assertCreated();

        $evaluation = TrialEvaluation::firstOrFail();
        $this->assertSame('Budi', $evaluation->participant_name);
        $this->assertSame($slot->starts_at->timezone('Asia/Jakarta')->toDateString(), $evaluation->trial_date->toDateString());
        $this->assertSame($this->trainer->id, $evaluation->trainer_id);
        $this->assertSame($this->bdg->id, $evaluation->branch_id);
    }

    public function test_staff_in_branch_evaluates_on_behalf_and_trainer_is_the_slot_trainer(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));

        $this->postJson('/api/trial-evaluations', $this->payload($lead))->assertCreated();

        $this->assertSame($this->trainer->id, TrialEvaluation::firstOrFail()->trainer_id);
    }

    public function test_trainer_cannot_evaluate_participant_of_another_trainers_slot(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->otherTrainer));
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', $this->payload($lead))
            ->assertStatus(422)->assertJsonValidationErrors('lead_id');
        $this->assertDatabaseCount('trial_evaluations', 0);
    }

    public function test_staff_cannot_evaluate_participant_of_another_branch(): void
    {
        $jktTrainer = $this->makeStaff('trainer', 'assigned', [$this->jkt->id]);
        $lead = $this->makeLead($this->makeSlot($this->jkt, $jktTrainer));
        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));

        $this->postJson('/api/trial-evaluations', $this->payload($lead))
            ->assertStatus(422)->assertJsonValidationErrors('lead_id');
        $this->assertDatabaseCount('trial_evaluations', 0);
    }

    public function test_lead_without_a_trial_slot_or_unknown_lead_is_rejected(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        $lead->update(['trial_slot_id' => null]);
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', $this->payload($lead))->assertStatus(422)->assertJsonValidationErrors('lead_id');
        $this->postJson('/api/trial-evaluations', $this->payload($lead, ['lead_id' => 99999]))->assertStatus(422)->assertJsonValidationErrors('lead_id');
        $this->postJson('/api/trial-evaluations', ['attendance' => 'hadir'])->assertStatus(422)->assertJsonValidationErrors('lead_id');
    }

    public function test_participant_can_only_be_evaluated_once(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', $this->payload($lead))->assertCreated();
        $this->postJson('/api/trial-evaluations', $this->payload($lead))
            ->assertStatus(422)->assertJsonValidationErrors('lead_id');
        $this->assertDatabaseCount('trial_evaluations', 1);
    }

    public function test_present_participant_requires_scores_and_recommendation(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', ['lead_id' => $lead->id, 'attendance' => 'hadir'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score_enthusiasm', 'score_understanding', 'score_focus', 'score_collaboration', 'recommendation']);
    }

    public function test_scores_must_be_between_one_and_five(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', $this->payload($lead, ['score_focus' => 6]))
            ->assertStatus(422)->assertJsonValidationErrors('score_focus');
        $this->postJson('/api/trial-evaluations', $this->payload($lead, ['score_focus' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('score_focus');
    }

    public function test_absent_participant_clears_scores_level_and_recommendation(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', $this->payload($lead, ['attendance' => 'tidak_hadir']))->assertCreated();

        $this->assertDatabaseHas('trial_evaluations', [
            'lead_id' => $lead->id, 'attendance' => 'tidak_hadir',
            'score_enthusiasm' => null, 'recommended_level_id' => null, 'recommendation' => null,
        ]);
    }

    public function test_recommended_level_must_belong_to_the_trial_program(): void
    {
        $otherLevel = $this->makeProgram('Robotik')->levels()->create(['name' => 'Lanjut', 'sort_order' => 1]);
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer));
        Sanctum::actingAs($this->trainer);

        $this->postJson('/api/trial-evaluations', $this->payload($lead, ['recommended_level_id' => $otherLevel->id]))
            ->assertStatus(422)->assertJsonValidationErrors('recommended_level_id');
        $this->postJson('/api/trial-evaluations', $this->payload($lead, ['recommended_level_id' => null]))
            ->assertCreated();
    }

    // ---------- Baca, ubah, hapus ----------

    public function test_show_is_404_outside_scope_and_200_inside(): void
    {
        $evaluation = $this->makeEvaluation($this->makeLead($this->makeSlot($this->bdg, $this->trainer)));
        $url = "/api/trial-evaluations/{$evaluation->id}";

        Sanctum::actingAs($this->trainer);
        $this->getJson($url)->assertOk()->assertJsonPath('data.id', $evaluation->id);

        Sanctum::actingAs($this->otherTrainer);
        $this->getJson($url)->assertStatus(404);

        Sanctum::actingAs($this->staff('assigned', [$this->jkt->id]));
        $this->getJson($url)->assertStatus(404);

        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));
        $this->getJson($url)->assertOk();

        Sanctum::actingAs($this->staff('all'));
        $this->getJson($url)->assertOk();
    }

    public function test_index_is_scoped_per_trainer_and_branch_and_legacy_rows_are_for_all_scope_only(): void
    {
        $this->makeEvaluation($this->makeLead($this->makeSlot($this->bdg, $this->trainer), 'Budi'));
        $this->makeEvaluation($this->makeLead($this->makeSlot($this->bdg, $this->otherTrainer), 'Siti'));
        $jktTrainer = $this->makeStaff('trainer', 'assigned', [$this->jkt->id]);
        $this->makeEvaluation($this->makeLead($this->makeSlot($this->jkt, $jktTrainer), 'Joko'));
        // Baris lama (sebelum Modul 3 tersambung ke lead): tanpa lead dan tanpa cabang.
        TrialEvaluation::create([
            'participant_name' => 'Lama', 'trainer_id' => $this->trainer->id, 'trial_date' => '2026-09-01', 'attendance' => 'tidak_hadir',
        ]);

        $names = fn () => collect($this->getJson('/api/trial-evaluations')->assertOk()->json('data'))->pluck('participant_name')->sort()->values()->all();

        Sanctum::actingAs($this->trainer);
        $this->assertSame(['Budi', 'Lama'], $names()); // miliknya sendiri (termasuk baris lama atas namanya)

        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));
        $this->assertSame(['Budi', 'Siti'], $names());

        Sanctum::actingAs($this->staff('all'));
        $this->assertSame(['Budi', 'Joko', 'Lama', 'Siti'], $names());
    }

    public function test_trainer_updates_own_evaluation_but_cannot_move_it_to_another_participant(): void
    {
        $lead = $this->makeLead($this->makeSlot($this->bdg, $this->trainer), 'Budi');
        $other = $this->makeLead($this->makeSlot($this->bdg, $this->trainer), 'Siti');
        $evaluation = $this->makeEvaluation($lead);
        Sanctum::actingAs($this->trainer);

        $this->putJson("/api/trial-evaluations/{$evaluation->id}", ['score_focus' => 2, 'lead_id' => $other->id, 'recommendation' => 'ragu'])
            ->assertOk()->assertJsonPath('data.score_focus', 2)->assertJsonPath('data.recommendation', 'ragu');

        $evaluation->refresh();
        $this->assertSame($lead->id, $evaluation->lead_id);
        $this->assertSame(2, $evaluation->score_focus);
    }

    public function test_update_and_delete_outside_scope_are_404_even_with_invalid_payload(): void
    {
        $evaluation = $this->makeEvaluation($this->makeLead($this->makeSlot($this->bdg, $this->trainer)));
        $url = "/api/trial-evaluations/{$evaluation->id}";

        foreach ([$this->otherTrainer, $this->staff('assigned', [$this->jkt->id])] as $intruder) {
            Sanctum::actingAs($intruder);
            $this->putJson($url, ['score_focus' => 99])->assertStatus(404);
            $this->deleteJson($url)->assertStatus(404);
        }
        $this->assertDatabaseCount('trial_evaluations', 1);

        Sanctum::actingAs($this->trainer);
        $this->deleteJson($url)->assertOk();
        $this->assertDatabaseCount('trial_evaluations', 0);
    }
}
