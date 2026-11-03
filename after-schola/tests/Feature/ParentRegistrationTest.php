<?php

namespace Tests\Feature;

use App\Models\Branch;
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
 * Portal Orang Tua (ORT-06, ORT-07; BR-01, BR-04, BR-05, AC-02, AC-03): pendaftaran resmi dengan auto-fill dari trial,
 * pilih paket, simpan draft, kirim untuk verifikasi, dan perbaiki bila diminta revisi.
 */
class ParentRegistrationTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    private User $parent;

    private Lead $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgramWithLevel('Coding Kids', 500000);
        $this->parent = $this->makeParent('Ibu Budi');
        $this->child = $this->makeChild($this->parent, $this->bdg, $this->program);
        Sanctum::actingAs($this->parent);
    }

    private function url(?Lead $child = null, string $suffix = ''): string
    {
        return '/api/parent/children/'.($child ?? $this->child)->id.'/registration'.$suffix;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'student_name' => 'Budi Santoso', 'birth_date' => '2015-05-05', 'origin_school' => 'SD Negeri 1', 'origin_class' => '5A',
            'parent_name' => 'Ibu Budi', 'parent_phone' => '081234567890', 'parent_email' => 'ibu@budi.test', 'parent_address' => 'Bandung',
        ], $overrides);
    }

    public function test_form_is_prefilled_from_the_trial_and_the_parent_account(): void
    {
        $this->decideFor($this->child);
        $this->child->trialEvaluation->update(['origin_class' => '5A']);

        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.status', 'belum_diisi')
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.form.student_name', 'Budi')
            ->assertJsonPath('data.form.origin_school', 'SD Negeri 1')
            ->assertJsonPath('data.form.origin_class', '5A')
            ->assertJsonPath('data.form.program_id', $this->program->id)
            ->assertJsonPath('data.form.parent_name', 'Ibu Budi')
            ->assertJsonPath('data.form.parent_phone', '081234567890')
            ->assertJsonPath('data.form.parent_email', $this->parent->email);
    }

    public function test_package_options_list_only_active_programs(): void
    {
        $this->decideFor($this->child);
        $other = $this->makeProgramWithLevel('Robotik', 700000);
        Program::create(['name' => 'Arsip', 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 100000, 'is_active' => false]);

        $names = collect($this->getJson($this->url())->assertOk()->json('options.programs'))->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Coding Kids', 'Robotik'], $names);
        $this->assertNotContains('Arsip', $names);
        $this->assertSame(700000, collect($this->getJson($this->url())->json('options.programs'))->firstWhere('id', $other->id)['price']);
    }

    public function test_registration_is_only_open_after_deciding_to_continue(): void
    {
        $this->getJson($this->url())->assertStatus(422)->assertJsonValidationErrors('decision'); // belum memutuskan

        $declined = $this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'Tolak');
        $this->decideFor($declined, 'tidak');
        $this->getJson($this->url($declined))->assertStatus(422);
        $this->putJson($this->url($declined), $this->payload())->assertStatus(422)->assertJsonValidationErrors('decision');

        $notEvaluated = $this->makeChild($this->parent, $this->bdg, $this->program, 'scheduled', 'Baru');
        $this->getJson($this->url($notEvaluated))->assertStatus(422);
    }

    public function test_saving_creates_a_draft_with_server_side_identity(): void
    {
        $this->decideFor($this->child);

        $this->putJson($this->url(), $this->payload([
            'lead_id' => 999, 'branch_id' => 999, 'level' => 'Hack', 'status' => 'terverifikasi', // tidak boleh dikendalikan klien
        ]))->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.form.student_name', 'Budi Santoso');

        $registration = Registration::sole();
        $this->assertSame($this->child->id, $registration->lead_id);
        $this->assertSame($this->bdg->id, $registration->branch_id);
        $this->assertSame('Dasar', $registration->level); // snapshot level rekomendasi trial
        $this->assertSame($this->program->id, $registration->program_id);
        $this->assertSame($this->parent->id, $registration->registered_by);
        $this->assertSame('draft', $registration->status);
    }

    public function test_saving_again_updates_the_same_draft_and_allows_choosing_another_package(): void
    {
        $this->decideFor($this->child);
        $robotik = $this->makeProgramWithLevel('Robotik', 700000);

        $this->putJson($this->url(), $this->payload())->assertOk();
        $this->putJson($this->url(), $this->payload(['student_name' => 'Budi S.', 'program_id' => $robotik->id]))->assertOk();

        $registration = Registration::sole();
        $this->assertSame('Budi S.', $registration->student_name);
        $this->assertSame($robotik->id, $registration->program_id);
    }

    public function test_inactive_program_cannot_be_chosen(): void
    {
        $this->decideFor($this->child);
        $inactive = Program::create(['name' => 'Arsip', 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 100000, 'is_active' => false]);

        $this->putJson($this->url(), $this->payload(['program_id' => $inactive->id]))
            ->assertStatus(422)->assertJsonValidationErrors('program_id');
    }

    public function test_required_fields_and_birth_date_are_validated(): void
    {
        $this->decideFor($this->child);

        $this->putJson($this->url(), [])->assertStatus(422)
            ->assertJsonValidationErrors(['student_name', 'parent_name', 'parent_phone']);
        $this->putJson($this->url(), $this->payload(['birth_date' => now()->addDay()->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('birth_date');
        $this->assertSame(0, Registration::count());
    }

    public function test_submitting_sends_the_registration_to_verification_and_locks_it(): void
    {
        $this->decideFor($this->child);
        $this->putJson($this->url(), $this->payload())->assertOk();

        $this->postJson($this->url(null, '/submit'))->assertOk()
            ->assertJsonPath('data.status', 'menunggu_verifikasi')
            ->assertJsonPath('data.editable', false);

        $this->putJson($this->url(), $this->payload(['student_name' => 'Diubah']))
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame('Budi Santoso', Registration::sole()->student_name);
        $this->getJson('/api/parent/children')->assertJsonPath('data.0.stage', 'menunggu_verifikasi');
    }

    public function test_cannot_submit_without_a_saved_registration(): void
    {
        $this->decideFor($this->child);

        $this->postJson($this->url(null, '/submit'))->assertStatus(422)->assertJsonValidationErrors('registration');
    }

    public function test_parent_sees_the_revision_note_fixes_the_data_and_resubmits(): void
    {
        $this->decideFor($this->child);
        $registration = $this->registrationFor($this->child, 'perlu_revisi');
        $registration->update(['revision_note' => 'Nomor telepon belum benar']);

        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.status', 'perlu_revisi')
            ->assertJsonPath('data.revision_note', 'Nomor telepon belum benar')
            ->assertJsonPath('data.editable', true);

        $this->putJson($this->url(), $this->payload(['parent_phone' => '081299998888']))->assertOk();
        $this->postJson($this->url(null, '/submit'))->assertOk()->assertJsonPath('data.status', 'menunggu_verifikasi');

        $this->assertSame('081299998888', $registration->fresh()->parent_phone);
    }

    public function test_submitted_registration_can_still_be_viewed_read_only(): void
    {
        $this->registrationFor($this->child, 'terverifikasi');

        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.status', 'terverifikasi')
            ->assertJsonPath('data.editable', false);
    }

    public function test_other_parents_and_staff_cannot_touch_the_registration(): void
    {
        $this->decideFor($this->child);
        $this->putJson($this->url(), $this->payload())->assertOk();

        Sanctum::actingAs($this->makeParent('Ibu Lain'));
        $this->getJson($this->url())->assertNotFound();
        $this->putJson($this->url(), $this->payload(['student_name' => 'Bajakan']))->assertNotFound();
        $this->postJson($this->url(null, '/submit'))->assertNotFound();
        $this->assertSame('draft', Registration::sole()->status);
        $this->assertSame('Budi Santoso', Registration::sole()->student_name);

        Sanctum::actingAs($this->makeStaff('management', 'all'));
        $this->getJson($this->url())->assertForbidden();
    }
}
