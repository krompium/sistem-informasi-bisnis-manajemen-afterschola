<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-02, ORT-03): daftar anak dengan status dan tindakan tertunggu, tambah anak untuk trial.
 * Isolasi: orang tua hanya melihat anaknya sendiri (BR-19, AC-07).
 */
class ParentChildrenTest extends TestCase
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
        $this->program = $this->makeProgramWithLevel();
        $this->parent = $this->makeParent();
        Sanctum::actingAs($this->parent);
    }

    private function stageOf(Lead $lead): array
    {
        $item = collect($this->getJson('/api/parent/children')->assertOk()->json('data'))->firstWhere('id', $lead->id);

        return [$item['stage'], $item['next_action']];
    }

    public function test_only_parents_can_use_the_portal_endpoints(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/parent/children')->assertUnauthorized();

        foreach (['management', 'trainer', 'finance'] as $role) {
            Sanctum::actingAs($this->makeStaff($role, 'all'));
            $this->getJson('/api/parent/children')->assertForbidden();
        }
    }

    public function test_lists_only_own_children_with_program_and_branch(): void
    {
        $mine = $this->makeChild($this->parent, $this->bdg, $this->program, 'scheduled', 'Budi');
        $this->makeChild($this->makeParent('Ibu Lain'), $this->bdg, $this->program, 'scheduled', 'Anak Orang');

        $res = $this->getJson('/api/parent/children')->assertOk();

        $this->assertSame([$mine->id], collect($res->json('data'))->pluck('id')->all());
        $res->assertJsonPath('data.0.name', 'Budi')
            ->assertJsonPath('data.0.program.name', 'Coding Kids')
            ->assertJsonPath('data.0.branch.name', 'Cabang Bandung');
    }

    public function test_stage_and_pending_action_follow_the_enrollment_journey(): void
    {
        $scheduled = $this->makeChild($this->parent, $this->bdg, $this->program, 'scheduled', 'A');
        $this->assertSame(['trial_terjadwal', null], $this->stageOf($scheduled));

        $evaluated = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'B');
        $this->assertSame(['menunggu_keputusan', 'lihat_hasil'], $this->stageOf($evaluated));

        $absent = $this->makeChild($this->parent, $this->bdg, $this->program, 'absent', 'C');
        $this->assertSame(['tidak_hadir', null], $this->stageOf($absent));

        $declined = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'D');
        $this->decideFor($declined, 'tidak');
        $this->assertSame(['tidak_lanjut', null], $this->stageOf($declined));

        $ready = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'E');
        $this->decideFor($ready);
        $this->assertSame(['siap_daftar', 'daftar'], $this->stageOf($ready));

        $draft = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'F');
        $this->registrationFor($draft, 'draft');
        $this->assertSame(['siap_daftar', 'daftar'], $this->stageOf($draft));

        $revision = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'G');
        $this->registrationFor($revision, 'perlu_revisi');
        $this->assertSame(['perlu_revisi', 'perbaiki_data'], $this->stageOf($revision));

        $waiting = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'H');
        $this->registrationFor($waiting, 'menunggu_verifikasi');
        $this->assertSame(['menunggu_verifikasi', null], $this->stageOf($waiting));

        $unpaid = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'I');
        $this->invoiceFor($this->registrationFor($unpaid, 'terverifikasi'));
        $this->assertSame(['menunggu_bayar', 'bayar'], $this->stageOf($unpaid));

        $paid = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'J');
        $this->invoiceFor($this->registrationFor($paid, 'terverifikasi'), 'paid');
        $this->assertSame(['siap_dijadwalkan', null], $this->stageOf($paid));

        $active = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'K');
        $registration = $this->registrationFor($active, 'terverifikasi');
        $this->invoiceFor($registration, 'paid');
        $this->enrollStudent($registration);
        $this->assertSame(['aktif', null], $this->stageOf($active));
    }

    public function test_voided_invoice_does_not_count_as_waiting_for_payment(): void
    {
        $child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $registration = $this->registrationFor($child, 'terverifikasi');
        $this->invoiceFor($registration, 'void');

        $this->assertSame(['menunggu_bayar', null], $this->stageOf($child));
    }

    private function childPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Adik Budi', 'education_level' => 'sd', 'origin_school' => 'SD Negeri 2',
            'trial_slot_id' => $this->makeSlot($this->bdg, $this->program)->id, 'consent' => true,
        ], $overrides);
    }

    public function test_parent_adds_another_child_for_trial_without_refilling_parent_data(): void
    {
        $res = $this->postJson('/api/parent/children', $this->childPayload())->assertCreated();

        $lead = Lead::findOrFail($res->json('data.id'));
        $this->assertSame($this->parent->id, $lead->parent_id);
        $this->assertSame('trial_scheduled', $lead->status);
        $this->assertSame($this->bdg->id, $lead->branch_id);
        $this->assertSame($this->program->id, $lead->program_id);
        $this->assertNotNull($lead->consented_at);
        $res->assertJsonPath('data.stage', 'trial_terjadwal');
        $this->assertSame('trial_registered', $this->parent->notifications()->sole()->data['kind']);
    }

    public function test_adding_a_child_requires_consent_and_a_bookable_slot(): void
    {
        $full = $this->makeSlot($this->bdg, $this->program, ['capacity' => 1]);
        Lead::create(['name' => 'X', 'education_level' => 'sd', 'origin_school' => 'SD', 'parent_email' => 'x@t.id', 'parent_id' => $this->makeParent('Ibu X')->id,
            'branch_id' => $this->bdg->id, 'program_id' => $this->program->id, 'trial_slot_id' => $full->id, 'status' => 'trial_scheduled']);
        $past = $this->makeSlot($this->bdg, $this->program, ['starts_at' => now()->subDay()]);
        $before = Lead::count();

        $this->postJson('/api/parent/children', $this->childPayload(['consent' => false]))
            ->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->postJson('/api/parent/children', $this->childPayload(['trial_slot_id' => $full->id]))
            ->assertStatus(422)->assertJsonValidationErrors('trial_slot_id');
        $this->postJson('/api/parent/children', $this->childPayload(['trial_slot_id' => $past->id]))
            ->assertStatus(422)->assertJsonValidationErrors('trial_slot_id');

        $this->assertSame($before, Lead::count());
    }

    public function test_adding_a_child_validates_required_fields(): void
    {
        $this->postJson('/api/parent/children', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'education_level', 'origin_school', 'trial_slot_id', 'consent']);
    }
}
