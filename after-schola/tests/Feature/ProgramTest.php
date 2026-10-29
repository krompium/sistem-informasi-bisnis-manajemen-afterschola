<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\TrialSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 1, slice 1: master program (MGT-10, MGT-13). Program yang sudah dipakai diarsipkan, bukan dihapus.
 */
class ProgramTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function asManagement(): void
    {
        Sanctum::actingAs($this->makeUser('management'));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Robotik Dasar',
            'jenjang' => 'SMP',
            'tipe' => 'online',
            'description' => 'Robotik untuk pemula.',
            'price' => 650000,
            'is_active' => true,
        ], $overrides);
    }

    public function test_guest_gets_401_and_roles_without_permission_get_403(): void
    {
        $program = $this->makeProgram();

        $this->getJson('/api/programs')->assertStatus(401);

        foreach (['trainer', 'finance', 'hr'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson('/api/programs')->assertStatus(403);
            $this->postJson('/api/programs', $this->validPayload())->assertStatus(403);
            $this->putJson("/api/programs/{$program->id}", ['name' => 'x'])->assertStatus(403);
            $this->deleteJson("/api/programs/{$program->id}")->assertStatus(403);
            $this->postJson("/api/programs/{$program->id}/jadwal-trial", [
                'hari' => 'senin', 'jam_mulai' => '15:00', 'jam_selesai' => '16:00',
            ])->assertStatus(403);
        }

        $this->assertDatabaseHas('programs', ['id' => $program->id, 'name' => 'Coding Kids']);
    }

    public function test_any_user_with_manage_programs_permission_has_access(): void
    {
        $user = $this->makeUser('hr');
        $user->givePermissionTo('manage programs');
        Sanctum::actingAs($user);

        $this->getJson('/api/programs')->assertOk();
    }

    public function test_index_lists_programs_with_prd_fields_and_trial_schedules(): void
    {
        $program = $this->makeProgram(750000);
        $program->jadwalTrial()->create(['hari' => 'senin', 'jam_mulai' => '15:30', 'jam_selesai' => '16:30']);
        $this->asManagement();

        $this->getJson('/api/programs')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Coding Kids')
            ->assertJsonPath('data.0.jenjang', 'SD')
            ->assertJsonPath('data.0.tipe', 'offline')
            ->assertJsonPath('data.0.price', '750000.00')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.0.jadwal_trial.0.hari', 'senin')
            ->assertJsonMissingPath('data.0.nama');
    }

    public function test_store_creates_program(): void
    {
        $this->asManagement();

        $this->postJson('/api/programs', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Robotik Dasar')
            ->assertJsonPath('data.jenjang', 'SMP')
            ->assertJsonPath('data.tipe', 'online')
            ->assertJsonPath('data.price', '650000.00')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('programs', ['name' => 'Robotik Dasar', 'jenjang' => 'SMP', 'is_active' => true]);
    }

    public function test_store_validates_input(): void
    {
        $this->asManagement();

        $this->postJson('/api/programs', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'jenjang', 'tipe', 'price']);

        $this->postJson('/api/programs', $this->validPayload(['jenjang' => 'TK']))
            ->assertStatus(422)->assertJsonValidationErrors('jenjang');
        $this->postJson('/api/programs', $this->validPayload(['tipe' => 'hybrid']))
            ->assertStatus(422)->assertJsonValidationErrors('tipe');
        $this->postJson('/api/programs', $this->validPayload(['price' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors('price');

        $this->assertDatabaseCount('programs', 0);
    }

    public function test_update_changes_only_sent_fields_and_can_archive(): void
    {
        $program = $this->makeProgram();
        $this->asManagement();

        $this->putJson("/api/programs/{$program->id}", ['price' => 900000, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.price', '900000.00')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name', 'Coding Kids');

        $this->putJson("/api/programs/{$program->id}", ['jenjang' => 'TK'])
            ->assertStatus(422)->assertJsonValidationErrors('jenjang');
    }

    public function test_destroy_removes_unused_program(): void
    {
        $program = $this->makeProgram();
        $this->asManagement();

        $this->deleteJson("/api/programs/{$program->id}")->assertStatus(204);

        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    public function test_destroy_is_rejected_when_program_is_used_by_registration_or_trial(): void
    {
        $program = $this->makeProgram();
        $this->makeRegistration([], $program); // membuat trial evaluation + registration untuk program ini
        $this->asManagement();

        $this->deleteJson("/api/programs/{$program->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('program');

        $this->assertDatabaseHas('programs', ['id' => $program->id]);
    }

    public function test_destroy_is_rejected_when_program_has_trial_slots(): void
    {
        $program = $this->makeProgram();
        TrialSlot::create([
            'branch_id' => Branch::create(['name' => 'Cabang Bandung', 'code' => 'BDG'])->id,
            'program_id' => $program->id,
            'trainer_id' => $this->makeUser('trainer')->id,
            'starts_at' => now()->addDay(),
            'capacity' => 5,
        ]);
        $this->asManagement();

        $this->deleteJson("/api/programs/{$program->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('program');

        $this->assertDatabaseHas('programs', ['id' => $program->id]);
    }

    public function test_destroy_is_rejected_when_program_is_used_by_lead(): void
    {
        $program = $this->makeProgram();
        Lead::create([
            'name' => 'Calon Siswa',
            'education_level' => 'sd',
            'origin_school' => 'SD Negeri 1',
            'parent_email' => 'ortu@example.com',
            'program_id' => $program->id,
        ]);
        $this->asManagement();

        $this->deleteJson("/api/programs/{$program->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('program');

        $this->assertDatabaseHas('programs', ['id' => $program->id]);
    }

    public function test_trial_schedule_can_be_added_and_removed(): void
    {
        $program = $this->makeProgram();
        $this->asManagement();

        $id = $this->postJson("/api/programs/{$program->id}/jadwal-trial", [
            'hari' => 'rabu', 'jam_mulai' => '15:30', 'jam_selesai' => '16:30',
        ])->assertStatus(201)->json('data.id');

        $this->deleteJson("/api/programs/{$program->id}/jadwal-trial/{$id}")->assertStatus(204);
        $this->assertDatabaseCount('program_trial_schedules', 0);
    }
}
