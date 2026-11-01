<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\TrialSlot;
use App\Notifications\TrialRegisteredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-02/03/04): daftar anak + status, tambah anak, hasil trial. Hanya data anak sendiri (BR-19, AC-07).
 */
class ParentStudentTest extends TestCase
{
    use MakesParentFixtures, RefreshDatabase;

    private $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->branch = $this->newBranch('BDG');
    }

    public function test_only_parent_role_can_use_the_portal(): void
    {
        $this->getJson('/api/parent/students')->assertUnauthorized();

        foreach (['management', 'trainer', 'finance'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson('/api/parent/students')->assertForbidden();
        }
    }

    public function test_lists_only_own_children(): void
    {
        $mine = $this->makeUser('parent');
        $other = $this->makeUser('parent');
        $own = $this->makeChild($mine, $this->branch, 'trial_terjadwal', 'Anak Saya');
        $this->makeChild($other, $this->branch, 'trial_terjadwal', 'Anak Orang Lain');
        Sanctum::actingAs($mine);

        $this->getJson('/api/parent/students')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.name', 'Anak Saya');
    }

    public function test_stage_follows_the_trial_to_payment_flow(): void
    {
        $parent = $this->makeUser('parent');
        Sanctum::actingAs($parent);

        $expected = [];
        foreach (['trial_terjadwal', 'hasil_trial', 'isi_pendaftaran', 'perlu_revisi', 'menunggu_verifikasi', 'menunggu_bayar', 'menunggu_konfirmasi', 'lunas'] as $stage) {
            $expected[$this->makeChild($parent, $this->branch, $stage)->id] = $stage;
        }
        $absent = $this->makeChild($parent, $this->branch, 'trial_terjadwal');
        $this->makeAbsentTrial($absent);
        $expected[$absent->id] = 'tidak_hadir';
        $declined = $this->makeChild($parent, $this->branch, 'hasil_trial');
        $declined->update(['status' => 'not_continued']);
        $expected[$declined->id] = 'tidak_lanjut';

        $actual = collect($this->getJson('/api/parent/students')->assertOk()->json('data'))->pluck('stage', 'id')->all();

        ksort($actual); // API mengurutkan anak terbaru dulu; yang diuji adalah tahap tiap anak
        $this->assertSame($expected, $actual);
    }

    public function test_detail_shows_trial_result_without_internal_recommendation(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'perlu_revisi');
        Sanctum::actingAs($parent);

        $res = $this->getJson("/api/parent/students/{$child->id}")->assertOk()
            ->assertJsonPath('data.stage', 'perlu_revisi')
            ->assertJsonPath('data.trial_result.strengths', 'Cepat paham logika')
            ->assertJsonPath('data.trial_result.recommended_level', 'Beginner')
            ->assertJsonPath('data.trial_result.scores.focus', 3)
            ->assertJsonPath('data.offer.price', 500000)
            ->assertJsonPath('data.decision.decision', 'lanjut')
            ->assertJsonPath('data.registration.revision_note', 'Nama sekolah belum lengkap')
            ->assertJsonPath('data.prefill.parent_email', $parent->email);

        $this->assertStringNotContainsString('recommendation', json_encode($res->json()));
    }

    public function test_detail_of_another_parents_child_is_404(): void
    {
        $theirs = $this->makeChild($this->makeUser('parent'), $this->branch, 'hasil_trial');
        Sanctum::actingAs($this->makeUser('parent'));

        $this->getJson("/api/parent/students/{$theirs->id}")->assertNotFound();
    }

    public function test_parent_can_add_another_child_for_a_trial_without_new_account(): void
    {
        Notification::fake();
        $parent = $this->makeUser('parent');
        $slot = $this->makeChild($this->makeUser('parent'), $this->branch)->trialSlot;
        Sanctum::actingAs($parent);
        $usersBefore = \App\Models\User::count();

        $this->postJson('/api/parent/students', [
            'name' => 'Adik Budi', 'education_level' => 'smp', 'origin_school' => 'SMPN 2', 'trial_slot_id' => $slot->id, 'consent' => true,
        ])->assertCreated()->assertJsonPath('data.name', 'Adik Budi')->assertJsonPath('data.stage', 'trial_terjadwal');

        $lead = Lead::where('name', 'Adik Budi')->firstOrFail();
        $this->assertSame($parent->id, $lead->parent_id);
        $this->assertSame($slot->branch_id, $lead->branch_id);
        $this->assertSame($usersBefore, \App\Models\User::count());
        Notification::assertSentTo($parent, TrialRegisteredNotification::class);
    }

    public function test_adding_a_child_requires_consent_and_a_bookable_slot(): void
    {
        $parent = $this->makeUser('parent');
        $slot = $this->makeChild($this->makeUser('parent'), $this->branch)->trialSlot;
        Sanctum::actingAs($parent);
        $payload = ['name' => 'Adik', 'education_level' => 'sd', 'origin_school' => 'SDN 3', 'trial_slot_id' => $slot->id];

        $this->postJson('/api/parent/students', $payload)->assertStatus(422)->assertJsonValidationErrors('consent');

        TrialSlot::whereKey($slot->id)->update(['capacity' => 1]); // sudah terisi satu anak lain
        $this->postJson('/api/parent/students', $payload + ['consent' => true])->assertStatus(422)->assertJsonValidationErrors('trial_slot_id');
    }
}
