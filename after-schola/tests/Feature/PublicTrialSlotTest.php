<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\TrialSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 2, slice 2.2a: slot trial yang masih bisa dipesan, untuk halaman publik (PUB-02).
 */
class PublicTrialSlotTest extends TestCase
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

    private function makeSlot(Branch $branch, array $overrides = []): TrialSlot
    {
        $attributes = array_merge([
            'branch_id' => $branch->id,
            'program_id' => $this->program->id,
            'starts_at' => now()->addDays(3),
            'capacity' => 8,
            'is_open' => true,
        ], $overrides);
        $attributes['trainer_id'] ??= $this->makeStaff('trainer', 'assigned', [$branch->id])->id;

        return TrialSlot::create($attributes);
    }

    private function book(TrialSlot $slot, int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            Lead::create([
                'name' => "Calon {$i}",
                'education_level' => 'sd',
                'origin_school' => 'SD Negeri 1',
                'parent_email' => "ortu{$i}@example.com",
                'program_id' => $slot->program_id,
                'trial_slot_id' => $slot->id,
                'status' => 'trial_scheduled',
            ]);
        }
    }

    private function list(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/public/trial-slots?'.http_build_query(array_merge(['program_id' => $this->program->id], $query)));
    }

    public function test_endpoint_is_public_and_requires_program_id(): void
    {
        $this->getJson('/api/public/trial-slots')
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_id');

        $this->list()->assertOk();
    }

    public function test_lists_only_slots_that_can_still_be_booked(): void
    {
        $open = $this->makeSlot($this->bdg);
        $this->makeSlot($this->bdg, ['is_open' => false]);
        $this->makeSlot($this->bdg, ['starts_at' => now()->subHour()]);
        $this->makeSlot($this->bdg, ['program_id' => $this->makeProgram('Robotik')->id]);
        $full = $this->makeSlot($this->bdg, ['capacity' => 1]);
        $this->book($full);
        $closedBranch = $this->makeBranch('Cabang Tutup', 'TTP', ['is_active' => false]);
        $this->makeSlot($closedBranch);

        $res = $this->list()->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($open->id, $res->json('data.0.id'));
    }

    public function test_slots_of_an_archived_program_are_hidden(): void
    {
        $archived = $this->makeProgram('Program Arsip', active: false);
        $this->makeSlot($this->bdg, ['program_id' => $archived->id]);

        $this->list(['program_id' => $archived->id])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_exposes_only_safe_fields(): void
    {
        $slot = $this->makeSlot($this->bdg, ['starts_at' => now()->addDays(3)->startOfMinute(), 'capacity' => 4]);
        $this->book($slot);

        $this->list()
            ->assertOk()
            ->assertJsonPath('data.0.id', $slot->id)
            ->assertJsonPath('data.0.program.name', 'Coding Kids')
            ->assertJsonPath('data.0.branch.name', 'Cabang Bandung')
            ->assertJsonPath('data.0.starts_at', $slot->starts_at->toIso8601String())
            ->assertJsonPath('data.0.remaining', 3)
            ->assertJsonMissingPath('data.0.trainer')
            ->assertJsonMissingPath('data.0.trainer_id')
            ->assertJsonMissingPath('data.0.capacity')
            ->assertJsonMissingPath('data.0.is_open');
    }

    public function test_orders_by_start_time_and_filters_by_branch(): void
    {
        $later = $this->makeSlot($this->jkt, ['starts_at' => now()->addDays(5)]);
        $sooner = $this->makeSlot($this->bdg, ['starts_at' => now()->addDays(2)]);

        $res = $this->list()->assertOk();
        $this->assertSame([$sooner->id, $later->id], collect($res->json('data'))->pluck('id')->all());

        $res = $this->list(['branch_id' => $this->jkt->id])->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($later->id, $res->json('data.0.id'));
    }
}
