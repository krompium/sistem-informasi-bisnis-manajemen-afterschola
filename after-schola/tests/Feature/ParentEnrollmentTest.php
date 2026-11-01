<?php

namespace Tests\Feature;

use App\Models\EnrollmentDecision;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-05/06/07): ortu memutuskan lanjut/tidak, mengisi pendaftaran dengan auto-fill, memperbaiki revisi (BR-02, AC-02/03).
 */
class ParentEnrollmentTest extends TestCase
{
    use MakesParentFixtures, RefreshDatabase;

    private $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->branch = $this->newBranch('BDG');
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'student_name' => 'Budi Santoso', 'birth_date' => '2016-05-01', 'origin_school' => 'SDN 1', 'origin_class' => '4',
            'parent_name' => 'Ibu Budi', 'parent_phone' => '081234567890', 'parent_email' => 'ibu@test.id', 'parent_address' => 'Bandung',
        ], $overrides);
    }

    public function test_parent_decides_to_continue_and_it_is_recorded_as_the_parents_decision(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'hasil_trial');
        Sanctum::actingAs($parent);

        $this->postJson("/api/parent/students/{$child->id}/decision", ['decision' => 'lanjut'])->assertOk()
            ->assertJsonPath('data.stage', 'isi_pendaftaran');

        $decision = EnrollmentDecision::firstOrFail();
        $this->assertSame('lanjut', $decision->decision);
        $this->assertSame($parent->id, $decision->decided_by);
    }

    public function test_declining_makes_the_child_a_lead_and_needs_a_reason(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'hasil_trial');
        Sanctum::actingAs($parent);

        $this->postJson("/api/parent/students/{$child->id}/decision", ['decision' => 'tidak'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson("/api/parent/students/{$child->id}/decision", ['decision' => 'tidak', 'reason' => 'Jadwal bentrok'])->assertOk()
            ->assertJsonPath('data.stage', 'tidak_lanjut');

        $this->assertSame('not_continued', $child->fresh()->status);
    }

    public function test_parent_can_change_a_decline_back_to_continue_before_registering(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'hasil_trial');
        Sanctum::actingAs($parent);

        $this->postJson("/api/parent/students/{$child->id}/decision", ['decision' => 'tidak', 'reason' => 'Jadwal bentrok'])->assertOk();
        $this->postJson("/api/parent/students/{$child->id}/decision", ['decision' => 'lanjut'])->assertOk()
            ->assertJsonPath('data.stage', 'isi_pendaftaran');

        $this->assertSame('trial_scheduled', $child->fresh()->status);
    }

    public function test_decision_needs_an_attended_trial_result(): void
    {
        $parent = $this->makeUser('parent');
        $scheduled = $this->makeChild($parent, $this->branch, 'trial_terjadwal');
        Sanctum::actingAs($parent);

        $this->postJson("/api/parent/students/{$scheduled->id}/decision", ['decision' => 'lanjut'])->assertStatus(422);
    }

    public function test_registration_is_prefilled_then_saved_and_submitted_for_verification(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'isi_pendaftaran');
        Sanctum::actingAs($parent);

        $this->getJson("/api/parent/students/{$child->id}")->assertOk()
            ->assertJsonPath('data.prefill.student_name', 'Budi Santoso')
            ->assertJsonPath('data.prefill.origin_school', 'SDN 1')
            ->assertJsonPath('data.prefill.parent_name', $parent->name);

        $this->putJson("/api/parent/students/{$child->id}/registration", $this->registrationPayload())->assertOk()
            ->assertJsonPath('data.stage', 'isi_pendaftaran'); // masih draft
        $this->postJson("/api/parent/students/{$child->id}/registration/submit")->assertOk()
            ->assertJsonPath('data.stage', 'menunggu_verifikasi');

        $registration = Registration::firstOrFail();
        $this->assertSame($child->id, $registration->lead_id);
        $this->assertSame($this->branch->id, $registration->branch_id); // cabang dari server, bukan klien
        $this->assertSame($parent->id, $registration->registered_by);
    }

    public function test_registration_requires_a_continue_decision(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'hasil_trial');
        Sanctum::actingAs($parent);

        $this->putJson("/api/parent/students/{$child->id}/registration", $this->registrationPayload())->assertStatus(422);
    }

    public function test_revision_loop_and_lock_while_waiting_for_verification(): void
    {
        $parent = $this->makeUser('parent');
        $revision = $this->makeChild($parent, $this->branch, 'perlu_revisi');
        $waiting = $this->makeChild($parent, $this->branch, 'menunggu_verifikasi', 'Kakak Budi');
        Sanctum::actingAs($parent);

        $this->putJson("/api/parent/students/{$revision->id}/registration", $this->registrationPayload(['origin_school' => 'SDN 1 Bandung']))->assertOk();
        $this->postJson("/api/parent/students/{$revision->id}/registration/submit")->assertOk()->assertJsonPath('data.stage', 'menunggu_verifikasi');

        $this->putJson("/api/parent/students/{$waiting->id}/registration", $this->registrationPayload())->assertStatus(422);
    }

    public function test_another_parent_gets_404_on_every_action(): void
    {
        $child = $this->makeChild($this->makeUser('parent'), $this->branch, 'isi_pendaftaran');
        Sanctum::actingAs($this->makeUser('parent'));

        $this->postJson("/api/parent/students/{$child->id}/decision", ['decision' => 'tidak', 'reason' => 'x'])->assertNotFound();
        $this->putJson("/api/parent/students/{$child->id}/registration", $this->registrationPayload())->assertNotFound();
        $this->postJson("/api/parent/students/{$child->id}/registration/submit")->assertNotFound();
        $this->assertSame('lanjut', EnrollmentDecision::firstOrFail()->decision);
    }

    public function test_parent_cannot_use_staff_enrollment_endpoints(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'hasil_trial');
        Sanctum::actingAs($parent);

        $this->getJson('/api/enrollments')->assertForbidden();
        $this->putJson('/api/trial-evaluations/'.$child->trialEvaluation->id.'/decision', ['decision' => 'lanjut'])->assertForbidden();
    }
}
