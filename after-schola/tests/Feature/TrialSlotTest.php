<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Program;
use App\Models\TrialSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 2, slice 2.1: slot trial berkapasitas per cabang (MGT-20).
 * Isolasi cabang (PRD §9): slot cabang lain -> 404; FK dari body divalidasi di Form Request -> 422.
 */
class TrialSlotTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgram('Coding Kids');
    }

    private function makeProgram(string $name, bool $active = true): Program
    {
        return Program::create(['name' => $name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => $active]);
    }

    /** Marketing: Management dengan permission fungsi `manage trial slots`. */
    private function marketing(string $scope = 'all', array $branchIds = []): User
    {
        return $this->makeStaff('management', $scope, $branchIds, ['manage trial slots']);
    }

    private function trainerOf(Branch $branch): User
    {
        return $this->makeStaff('trainer', 'assigned', [$branch->id]);
    }

    private function makeSlot(Branch $branch, array $overrides = []): TrialSlot
    {
        $attributes = array_merge([
            'branch_id' => $branch->id,
            'program_id' => $this->program->id,
            'starts_at' => now()->addDays(3),
            'capacity' => 8,
            'is_open' => true,
        ], $overrides);
        $attributes['trainer_id'] ??= $this->trainerOf($branch)->id;

        return TrialSlot::create($attributes);
    }

    private function payload(array $overrides = []): array
    {
        $payload = array_merge([
            'branch_id' => $this->bdg->id,
            'program_id' => $this->program->id,
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'capacity' => 8,
        ], $overrides);
        $payload['trainer_id'] ??= $this->trainerOf($this->bdg)->id;

        return $payload;
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/trial-slots')->assertStatus(401);
        $this->postJson('/api/trial-slots', [])->assertStatus(401);
    }

    public function test_users_without_manage_trial_slots_permission_get_403(): void
    {
        $slot = $this->makeSlot($this->bdg);
        $payload = $this->payload();

        // Management tanpa permission fungsi juga ditolak: akses lewat permission, bukan role.
        foreach (['trainer', 'finance', 'parent', 'management'] as $role) {
            Sanctum::actingAs($this->makeStaff($role, 'all'));
            $this->getJson('/api/trial-slots')->assertStatus(403);
            $this->getJson('/api/trial-slots/options')->assertStatus(403);
            $this->postJson('/api/trial-slots', $payload)->assertStatus(403);
            $this->getJson("/api/trial-slots/{$slot->id}")->assertStatus(403);
            $this->putJson("/api/trial-slots/{$slot->id}", $payload)->assertStatus(403);
            $this->deleteJson("/api/trial-slots/{$slot->id}")->assertStatus(403);
        }

        $this->assertDatabaseCount('trial_slots', 1);
    }

    public function test_options_return_only_choices_within_the_users_scope(): void
    {
        $this->makeBranch('Cabang Tutup', 'TTP', ['is_active' => false]);
        $this->makeProgram('Program Arsip', active: false);
        $bdgTrainer = $this->trainerOf($this->bdg);
        $this->trainerOf($this->jkt);
        $central = $this->makeStaff('trainer', 'all');
        $inactive = $this->trainerOf($this->bdg);
        $inactive->update(['is_active' => false]);

        Sanctum::actingAs($this->marketing('assigned', [$this->bdg->id]));
        $res = $this->getJson('/api/trial-slots/options')->assertOk();

        $this->assertSame(['Cabang Bandung'], collect($res->json('data.branches'))->pluck('name')->all());
        $this->assertSame(['Coding Kids'], collect($res->json('data.programs'))->pluck('name')->all());
        $trainers = collect($res->json('data.trainers'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$bdgTrainer->id, $central->id], $trainers->keys()->all());
        $this->assertSame([$this->bdg->id], $trainers[$central->id]['branch_ids']);
    }

    public function test_options_for_all_scope_list_every_active_branch(): void
    {
        $jktTrainer = $this->trainerOf($this->jkt);
        Sanctum::actingAs($this->marketing('all'));

        $res = $this->getJson('/api/trial-slots/options')->assertOk();

        $this->assertCount(2, $res->json('data.branches'));
        $this->assertSame([$this->jkt->id], collect($res->json('data.trainers'))->firstWhere('id', $jktTrainer->id)['branch_ids']);
    }

    public function test_store_creates_slot_with_relations(): void
    {
        $trainer = $this->trainerOf($this->bdg);
        $startsAt = now()->addDays(3)->startOfMinute();
        Sanctum::actingAs($this->marketing('assigned', [$this->bdg->id]));

        $this->postJson('/api/trial-slots', $this->payload([
            'trainer_id' => $trainer->id,
            'starts_at' => $startsAt->toIso8601String(),
            'capacity' => 6,
        ]))
            ->assertStatus(201)
            ->assertJsonPath('data.branch.id', $this->bdg->id)
            ->assertJsonPath('data.branch.name', 'Cabang Bandung')
            ->assertJsonPath('data.program.name', 'Coding Kids')
            ->assertJsonPath('data.trainer.id', $trainer->id)
            ->assertJsonPath('data.capacity', 6)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.starts_at', $startsAt->toIso8601String());

        $this->assertDatabaseHas('trial_slots', [
            'branch_id' => $this->bdg->id,
            'program_id' => $this->program->id,
            'trainer_id' => $trainer->id,
            'capacity' => 6,
            'is_open' => true,
        ]);
    }

    public function test_store_requires_all_fields(): void
    {
        Sanctum::actingAs($this->marketing());

        $this->postJson('/api/trial-slots', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id', 'program_id', 'trainer_id', 'starts_at', 'capacity']);
    }

    public function test_store_rejects_invalid_capacity_past_time_and_unknown_relations(): void
    {
        Sanctum::actingAs($this->marketing());
        $payload = $this->payload();

        $this->postJson('/api/trial-slots', array_merge($payload, ['capacity' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('capacity');
        $this->postJson('/api/trial-slots', array_merge($payload, ['capacity' => 101]))
            ->assertStatus(422)->assertJsonValidationErrors('capacity');
        $this->postJson('/api/trial-slots', array_merge($payload, ['starts_at' => now()->subDay()->toIso8601String()]))
            ->assertStatus(422)->assertJsonValidationErrors('starts_at');
        $this->postJson('/api/trial-slots', array_merge($payload, ['program_id' => 999999]))
            ->assertStatus(422)->assertJsonValidationErrors('program_id');
        $this->postJson('/api/trial-slots', array_merge($payload, ['branch_id' => 999999]))
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseCount('trial_slots', 0);
    }

    public function test_store_rejects_inactive_program_and_inactive_branch(): void
    {
        $archived = $this->makeProgram('Program Arsip', active: false);
        $closed = $this->makeBranch('Cabang Tutup', 'TTP', ['is_active' => false]);
        Sanctum::actingAs($this->marketing());

        $this->postJson('/api/trial-slots', $this->payload(['program_id' => $archived->id]))
            ->assertStatus(422)->assertJsonValidationErrors('program_id');
        $this->postJson('/api/trial-slots', $this->payload([
            'branch_id' => $closed->id,
            'trainer_id' => $this->trainerOf($closed)->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseCount('trial_slots', 0);
    }

    public function test_trainer_must_be_an_active_trainer_assigned_to_the_branch(): void
    {
        $inactive = $this->trainerOf($this->bdg);
        $inactive->update(['is_active' => false]);
        $invalid = [
            $this->trainerOf($this->jkt),          // trainer cabang lain
            $this->makeStaff('finance', 'all'),    // bukan trainer
            $inactive,                             // trainer nonaktif
        ];
        Sanctum::actingAs($this->marketing());

        foreach ($invalid as $user) {
            $this->postJson('/api/trial-slots', $this->payload(['trainer_id' => $user->id]))
                ->assertStatus(422)->assertJsonValidationErrors('trainer_id');
        }
        $this->assertDatabaseCount('trial_slots', 0);

        // Trainer pusat (scope all) boleh dipasang di cabang mana pun.
        $central = $this->makeStaff('trainer', 'all');
        $this->postJson('/api/trial-slots', $this->payload(['trainer_id' => $central->id]))->assertStatus(201);
    }

    public function test_assigned_staff_cannot_create_slot_in_another_branch(): void
    {
        Sanctum::actingAs($this->marketing('assigned', [$this->bdg->id]));

        $this->postJson('/api/trial-slots', $this->payload([
            'branch_id' => $this->jkt->id,
            'trainer_id' => $this->trainerOf($this->jkt)->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseCount('trial_slots', 0);
    }

    public function test_index_is_limited_to_users_branches_and_all_scope_sees_everything(): void
    {
        $this->makeSlot($this->bdg);
        $this->makeSlot($this->jkt);

        Sanctum::actingAs($this->marketing('assigned', [$this->bdg->id]));
        $res = $this->getJson('/api/trial-slots')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($this->bdg->id, $res->json('data.0.branch.id'));
        // Filter ke cabang di luar cakupan tidak membocorkan apa pun.
        $this->getJson("/api/trial-slots?branch_id={$this->jkt->id}")->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->marketing('all'));
        $this->getJson('/api/trial-slots')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/trial-slots?branch_id={$this->jkt->id}")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_program_and_period(): void
    {
        $robotics = $this->makeProgram('Robotik');
        $this->makeSlot($this->bdg, ['starts_at' => now()->addDays(5), 'program_id' => $robotics->id]);
        $this->makeSlot($this->bdg, ['starts_at' => now()->addDays(2)]);
        $this->makeSlot($this->bdg, ['starts_at' => now()->subDays(2)]);
        Sanctum::actingAs($this->marketing());

        $this->getJson("/api/trial-slots?program_id={$robotics->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/trial-slots?period=past')->assertOk()->assertJsonCount(1, 'data');

        // Mendatang: yang terdekat lebih dulu.
        $this->getJson('/api/trial-slots?period=upcoming')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.program.name', 'Coding Kids')
            ->assertJsonPath('data.1.program.name', 'Robotik');
    }

    public function test_index_is_paginated_25_per_page(): void
    {
        $trainer = $this->trainerOf($this->bdg);
        for ($i = 0; $i < 26; $i++) {
            $this->makeSlot($this->bdg, ['trainer_id' => $trainer->id]);
        }
        Sanctum::actingAs($this->marketing());

        $this->getJson('/api/trial-slots')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 26);
    }

    public function test_slot_of_another_branch_is_404_for_show_update_and_delete(): void
    {
        $slot = $this->makeSlot($this->jkt);
        Sanctum::actingAs($this->marketing('assigned', [$this->bdg->id]));

        $this->getJson("/api/trial-slots/{$slot->id}")->assertStatus(404);
        $this->putJson("/api/trial-slots/{$slot->id}", $this->payload(['capacity' => 20]))->assertStatus(404);
        $this->deleteJson("/api/trial-slots/{$slot->id}")->assertStatus(404);
        $this->assertDatabaseHas('trial_slots', ['id' => $slot->id, 'capacity' => 8]);

        // Staf pusat boleh.
        Sanctum::actingAs($this->marketing('all'));
        $this->getJson("/api/trial-slots/{$slot->id}")->assertOk()->assertJsonPath('data.branch.id', $this->jkt->id);
    }

    public function test_update_changes_fields_and_a_past_slot_stays_editable(): void
    {
        $slot = $this->makeSlot($this->bdg, ['starts_at' => now()->subDay()->startOfMinute()]);
        Sanctum::actingAs($this->marketing());

        $this->putJson("/api/trial-slots/{$slot->id}", $this->payload([
            'trainer_id' => $slot->trainer_id,
            'starts_at' => $slot->starts_at->toIso8601String(),
            'capacity' => 12,
            'is_open' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('data.capacity', 12)
            ->assertJsonPath('data.is_open', false);

        $this->assertDatabaseHas('trial_slots', ['id' => $slot->id, 'capacity' => 12, 'is_open' => false]);
    }

    public function test_destroy_removes_slot(): void
    {
        $slot = $this->makeSlot($this->bdg);
        Sanctum::actingAs($this->marketing());

        $this->deleteJson("/api/trial-slots/{$slot->id}")->assertStatus(204);

        $this->assertDatabaseMissing('trial_slots', ['id' => $slot->id]);
    }
}
