<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\EnrollmentDecision;
use App\Models\Lead;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Registration;
use App\Models\TrialEvaluation;
use App\Models\TrialSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 4 (Fase 2): keputusan dicatat staf atas nama orang tua (BR-02, sampai portal orang tua ada),
 * leads (BR-03), auto-fill pendaftaran resmi (BR-01, AC-02), guard BR-04, dan isolasi cabang (BR-19).
 */
class EnrollmentFlowTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    private ProgramLevel $level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgram('Coding Kids');
        $this->level = $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
    }

    private function makeProgram(string $name, bool $active = true): Program
    {
        return Program::create(['name' => $name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => $active]);
    }

    /** Peserta trial lengkap: trainer, slot, orang tua, lead, dan evaluasi. */
    private function makeTrial(Branch $branch, string $name = 'Budi', string $attendance = 'hadir'): TrialEvaluation
    {
        $trainer = $this->makeStaff('trainer', 'assigned', [$branch->id]);
        $slot = TrialSlot::create([
            'branch_id' => $branch->id, 'program_id' => $this->program->id, 'trainer_id' => $trainer->id,
            'starts_at' => now()->subDays(2), 'capacity' => 10,
        ]);
        $parent = $this->makeStaff('parent');
        $parent->update(['name' => "Ibu {$name}", 'phone' => '081234567890']);
        $lead = Lead::create([
            'name' => $name, 'education_level' => 'sd', 'origin_school' => 'SD Negeri 1',
            'parent_email' => $parent->email, 'parent_id' => $parent->id,
            'branch_id' => $branch->id, 'program_id' => $this->program->id, 'trial_slot_id' => $slot->id,
            'status' => 'trial_scheduled',
        ]);

        return TrialEvaluation::create([
            'lead_id' => $lead->id, 'branch_id' => $branch->id, 'trial_slot_id' => $slot->id,
            'program_id' => $this->program->id, 'participant_name' => $name, 'origin_school' => 'SD Negeri 1',
            'trainer_id' => $trainer->id, 'trial_date' => today()->subDays(2), 'attendance' => $attendance,
            'recommended_level_id' => $attendance === 'hadir' ? $this->level->id : null,
            'recommendation' => $attendance === 'hadir' ? 'lanjut' : null,
        ]);
    }

    private function staff(string $scope = 'all', array $branchIds = [], array $permissions = []): User
    {
        return $this->makeStaff('management', $scope, $branchIds, $permissions);
    }

    private function decide(TrialEvaluation $evaluation, string $decision = 'lanjut', array $extra = [])
    {
        return $this->putJson("/api/trial-evaluations/{$evaluation->id}/decision", ['decision' => $decision] + $extra);
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'student_name' => 'Budi Santoso', 'birth_date' => '2015-05-05', 'origin_school' => 'SD Negeri 1', 'origin_class' => '5A',
            'parent_name' => 'Ibu Budi', 'parent_phone' => '081234567890', 'parent_email' => 'ibu@budi.test', 'parent_address' => 'Bandung',
        ], $overrides);
    }

    // ---------- Keputusan (BR-02 sementara oleh staf, BR-03, BR-04) ----------

    public function test_decision_tidak_turns_lead_into_leads_without_deleting_data(): void
    {
        $evaluation = $this->makeTrial($this->bdg);
        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));

        $this->decide($evaluation, 'tidak', ['reason' => 'Jadwal bentrok'])
            ->assertOk()->assertJsonPath('data.decision.decision', 'tidak');

        $this->assertSame('not_continued', $evaluation->lead->fresh()->status);
        $this->assertDatabaseHas('trial_evaluations', ['id' => $evaluation->id]);
        $this->assertDatabaseHas('leads', ['id' => $evaluation->lead_id, 'name' => 'Budi']);
        $this->assertDatabaseHas('enrollment_decisions', ['evaluation_id' => $evaluation->id, 'decision' => 'tidak']);
    }

    public function test_changing_decision_back_from_tidak_restores_trial_scheduled(): void
    {
        $evaluation = $this->makeTrial($this->bdg);
        Sanctum::actingAs($this->staff('all'));

        $this->decide($evaluation, 'tidak', ['reason' => 'Ragu'])->assertOk();
        $this->decide($evaluation, 'lanjut')->assertOk();

        $this->assertSame('trial_scheduled', $evaluation->lead->fresh()->status);
    }

    public function test_decision_lanjut_and_pikir_pikir_do_not_change_lead_status(): void
    {
        $a = $this->makeTrial($this->bdg, 'Ani');
        $b = $this->makeTrial($this->bdg, 'Bayu');
        Sanctum::actingAs($this->staff('all'));

        $this->decide($a, 'lanjut')->assertOk();
        $this->decide($b, 'pikir_pikir', ['follow_up_date' => today()->addDays(3)->toDateString()])->assertOk();

        $this->assertSame('trial_scheduled', $a->lead->fresh()->status);
        $this->assertSame('trial_scheduled', $b->lead->fresh()->status);
    }

    public function test_decision_needs_reason_for_tidak_and_follow_up_for_pikir_pikir(): void
    {
        $evaluation = $this->makeTrial($this->bdg);
        Sanctum::actingAs($this->staff('all'));

        $this->decide($evaluation, 'tidak')->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->decide($evaluation, 'pikir_pikir')->assertStatus(422)->assertJsonValidationErrors('follow_up_date');
        $this->assertSame('trial_scheduled', $evaluation->lead->fresh()->status);
    }

    public function test_decision_is_rejected_when_participant_did_not_attend(): void
    {
        $evaluation = $this->makeTrial($this->bdg, 'Absen', 'tidak_hadir');
        Sanctum::actingAs($this->staff('all'));

        $this->decide($evaluation)->assertStatus(422)->assertJsonValidationErrors('decision');
        $this->assertDatabaseCount('enrollment_decisions', 0);
    }

    // ---------- Daftar pipeline + auto-fill (BR-01, AC-02) ----------

    public function test_enrollment_list_exposes_prefill_from_lead_parent_and_trial(): void
    {
        $evaluation = $this->makeTrial($this->bdg, 'Budi');
        $parent = $evaluation->lead->parent;
        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));

        $this->getJson('/api/enrollments')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recommended_level', 'Dasar')
            ->assertJsonPath('data.0.prefill.student_name', 'Budi')
            ->assertJsonPath('data.0.prefill.origin_school', 'SD Negeri 1')
            ->assertJsonPath('data.0.prefill.program_id', $this->program->id)
            ->assertJsonPath('data.0.prefill.parent_name', 'Ibu Budi')
            ->assertJsonPath('data.0.prefill.parent_phone', '081234567890')
            ->assertJsonPath('data.0.prefill.parent_email', $parent->email);
    }

    public function test_enrollment_list_is_scoped_to_branch_and_legacy_rows_are_for_all_scope_only(): void
    {
        $this->makeTrial($this->bdg, 'Budi');
        $this->makeTrial($this->jkt, 'Joko');
        $this->makeTrial($this->bdg, 'Absen', 'tidak_hadir');
        TrialEvaluation::create([ // baris lama: tanpa cabang dan lead
            'participant_name' => 'Lama', 'trainer_id' => User::role('trainer')->first()->id,
            'trial_date' => '2026-09-01', 'attendance' => 'hadir',
        ]);
        $names = fn () => collect($this->getJson('/api/enrollments')->assertOk()->json('data'))->pluck('participant_name')->sort()->values()->all();

        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));
        $this->assertSame(['Budi'], $names());

        Sanctum::actingAs($this->staff('all'));
        $this->assertSame(['Budi', 'Joko', 'Lama'], $names());
    }

    // ---------- Pendaftaran resmi (BR-04) ----------

    public function test_registration_requires_decision_lanjut(): void
    {
        $evaluation = $this->makeTrial($this->bdg);
        Sanctum::actingAs($this->staff('all'));
        $url = "/api/trial-evaluations/{$evaluation->id}/registration";

        $this->putJson($url, $this->registrationPayload())->assertStatus(422)->assertJsonValidationErrors('decision');

        $this->decide($evaluation, 'tidak', ['reason' => 'x'])->assertOk();
        $this->putJson($url, $this->registrationPayload())->assertStatus(422)->assertJsonValidationErrors('decision');
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_registration_takes_level_branch_and_lead_from_the_server_not_the_client(): void
    {
        $evaluation = $this->makeTrial($this->bdg);
        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id]));
        $this->decide($evaluation)->assertOk();

        $this->putJson("/api/trial-evaluations/{$evaluation->id}/registration", $this->registrationPayload([
            'level' => 'Hacker', 'branch_id' => $this->jkt->id, 'lead_id' => 999, 'status' => 'terverifikasi',
        ]))->assertOk()
            ->assertJsonPath('data.registration.level', 'Dasar')
            ->assertJsonPath('data.registration.status', 'draft');

        $this->assertDatabaseHas('registrations', [
            'lead_id' => $evaluation->lead_id,
            'branch_id' => $this->bdg->id,
            'program_id' => $this->program->id, // default: program slot trial
            'level' => 'Dasar',
            'status' => 'draft',
        ]);
    }

    public function test_registration_package_can_be_changed_to_another_active_program_only(): void
    {
        $evaluation = $this->makeTrial($this->bdg);
        $other = $this->makeProgram('Robotik');
        $archived = $this->makeProgram('Lama', false);
        Sanctum::actingAs($this->staff('all'));
        $this->decide($evaluation)->assertOk();
        $url = "/api/trial-evaluations/{$evaluation->id}/registration";

        $this->putJson($url, $this->registrationPayload(['program_id' => $archived->id]))
            ->assertStatus(422)->assertJsonValidationErrors('program_id');
        $this->putJson($url, $this->registrationPayload(['program_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('program_id');
        $this->putJson($url, $this->registrationPayload(['program_id' => $other->id]))->assertOk();

        $this->assertDatabaseHas('registrations', ['lead_id' => $evaluation->lead_id, 'program_id' => $other->id]);
    }

    public function test_registration_can_be_submitted_for_verification_once(): void
    {
        $evaluation = $this->makeTrial($this->bdg);
        Sanctum::actingAs($this->staff('all'));
        $this->decide($evaluation)->assertOk();
        $url = "/api/trial-evaluations/{$evaluation->id}/registration";

        $this->postJson("{$url}/submit")->assertStatus(422)->assertJsonValidationErrors('registration');
        $this->putJson($url, $this->registrationPayload())->assertOk();
        $this->postJson("{$url}/submit")->assertOk()->assertJsonPath('data.registration.status', 'menunggu_verifikasi');
        $this->postJson("{$url}/submit")->assertStatus(422)->assertJsonValidationErrors('status');
        $this->putJson($url, $this->registrationPayload())->assertStatus(422)->assertJsonValidationErrors('status');
    }

    // ---------- Isolasi (BR-19) ----------

    public function test_staff_of_another_branch_gets_404_on_decision_registration_and_submit(): void
    {
        $evaluation = $this->makeTrial($this->bdg);
        $this->decide($evaluation)->assertStatus(401);
        Sanctum::actingAs($this->staff('assigned', [$this->jkt->id]));
        $base = "/api/trial-evaluations/{$evaluation->id}";

        // Payload sengaja tidak valid: otorisasi (404) harus lebih dulu dari validasi (422).
        $this->putJson("{$base}/decision", ['decision' => 'bogus'])->assertStatus(404);
        $this->putJson("{$base}/registration", [])->assertStatus(404);
        $this->postJson("{$base}/registration/submit")->assertStatus(404);

        $this->assertDatabaseCount('enrollment_decisions', 0);
        $this->assertDatabaseCount('registrations', 0);
        $this->assertSame('trial_scheduled', $evaluation->lead->fresh()->status);
    }

    public function test_trainer_and_parent_cannot_use_enrollment_endpoints(): void
    {
        $evaluation = $this->makeTrial($this->bdg);

        foreach (['trainer', 'parent'] as $role) {
            Sanctum::actingAs($this->makeStaff($role, 'assigned', [$this->bdg->id]));
            $this->getJson('/api/enrollments')->assertStatus(403);
            $this->decide($evaluation)->assertStatus(403);
            $this->putJson("/api/trial-evaluations/{$evaluation->id}/registration", $this->registrationPayload())->assertStatus(403);
        }
    }

    // ---------- Antrian verifikasi per cabang (BR-19) ----------

    private function makeSubmitted(TrialEvaluation $evaluation): Registration
    {
        $decision = EnrollmentDecision::create(['evaluation_id' => $evaluation->id, 'decision' => 'lanjut', 'decided_at' => today()]);

        return Registration::create([
            'decision_id' => $decision->id, 'lead_id' => $evaluation->lead_id, 'branch_id' => $evaluation->branch_id,
            'program_id' => $this->program->id, 'student_name' => $evaluation->participant_name, 'level' => 'Dasar',
            'parent_name' => 'Ibu', 'parent_phone' => '0812', 'status' => 'menunggu_verifikasi',
        ]);
    }

    public function test_verification_queue_lists_only_registrations_of_own_branches(): void
    {
        $this->makeSubmitted($this->makeTrial($this->bdg, 'Budi'));
        $this->makeSubmitted($this->makeTrial($this->jkt, 'Joko'));
        $names = fn () => collect($this->getJson('/api/registrations')->assertOk()->json('data'))->pluck('student_name')->sort()->values()->all();

        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id], ['verify registrations']));
        $this->assertSame(['Budi'], $names());

        Sanctum::actingAs($this->staff('all', [], ['verify registrations']));
        $this->assertSame(['Budi', 'Joko'], $names());
    }

    public function test_verification_of_another_branchs_registration_is_404(): void
    {
        $jktReg = $this->makeSubmitted($this->makeTrial($this->jkt, 'Joko'));
        $bdgReg = $this->makeSubmitted($this->makeTrial($this->bdg, 'Budi'));
        Sanctum::actingAs($this->staff('assigned', [$this->bdg->id], ['verify registrations']));

        $this->postJson("/api/registrations/{$jktReg->id}/approve")->assertStatus(404);
        $this->postJson("/api/registrations/{$jktReg->id}/request-revision", [])->assertStatus(404);
        $this->assertSame('menunggu_verifikasi', $jktReg->fresh()->status);
        $this->assertDatabaseCount('invoices', 0);

        $this->postJson("/api/registrations/{$bdgReg->id}/approve")->assertOk();
    }
}
