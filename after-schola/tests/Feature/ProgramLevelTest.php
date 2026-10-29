<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ProgramLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 1, slice 2: level per program (MGT-11).
 */
class ProgramLevelTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->program = $this->makeProgram();
    }

    private function asManagement(): void
    {
        Sanctum::actingAs($this->makeUser('management'));
    }

    private function url(string $suffix = ''): string
    {
        return "/api/programs/{$this->program->id}/levels{$suffix}";
    }

    public function test_guest_gets_401_and_roles_without_permission_get_403(): void
    {
        $level = $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);

        $this->getJson($this->url())->assertStatus(401);

        foreach (['trainer', 'finance'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson($this->url())->assertStatus(403);
            $this->postJson($this->url(), ['name' => 'Lanjut'])->assertStatus(403);
            $this->putJson($this->url("/{$level->id}"), ['name' => 'X'])->assertStatus(403);
            $this->deleteJson($this->url("/{$level->id}"))->assertStatus(403);
        }

        $this->assertDatabaseCount('program_levels', 1);
    }

    public function test_index_lists_only_this_programs_levels_ordered_by_sort_order(): void
    {
        $this->program->levels()->create(['name' => 'Lanjut', 'sort_order' => 2]);
        $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
        $other = Program::create(['name' => 'Lain', 'tipe' => 'online', 'price' => 1, 'is_active' => true]);
        $other->levels()->create(['name' => 'Level Lain', 'sort_order' => 1]);
        $this->asManagement();

        $names = collect($this->getJson($this->url())->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Dasar', 'Lanjut'], $names);
    }

    public function test_program_resource_embeds_levels_in_order(): void
    {
        $this->program->levels()->create(['name' => 'Lanjut', 'sort_order' => 2]);
        $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
        $this->asManagement();

        $this->getJson("/api/programs/{$this->program->id}")
            ->assertOk()
            ->assertJsonPath('data.levels.0.name', 'Dasar')
            ->assertJsonPath('data.levels.1.name', 'Lanjut');
    }

    public function test_store_creates_level(): void
    {
        $this->asManagement();

        $this->postJson($this->url(), ['name' => 'Dasar', 'sort_order' => 1])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Dasar')
            ->assertJsonPath('data.sort_order', 1);

        $this->assertDatabaseHas('program_levels', ['program_id' => $this->program->id, 'name' => 'Dasar']);
    }

    public function test_store_validates_name_and_uniqueness_per_program(): void
    {
        $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
        $this->asManagement();

        $this->postJson($this->url(), [])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson($this->url(), ['name' => 'Dasar'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson($this->url(), ['name' => 'X', 'sort_order' => -1])->assertStatus(422)->assertJsonValidationErrors('sort_order');

        // nama yang sama boleh di program lain
        $other = Program::create(['name' => 'Lain', 'tipe' => 'online', 'price' => 1, 'is_active' => true]);
        $this->postJson("/api/programs/{$other->id}/levels", ['name' => 'Dasar'])->assertStatus(201);
    }

    public function test_update_allows_own_name_but_rejects_sibling_name(): void
    {
        $dasar = $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
        $this->program->levels()->create(['name' => 'Lanjut', 'sort_order' => 2]);
        $this->asManagement();

        $this->putJson($this->url("/{$dasar->id}"), ['name' => 'Dasar', 'sort_order' => 5])
            ->assertOk()->assertJsonPath('data.sort_order', 5);

        $this->putJson($this->url("/{$dasar->id}"), ['name' => 'Lanjut'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_destroy_removes_level(): void
    {
        $level = $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
        $this->asManagement();

        $this->deleteJson($this->url("/{$level->id}"))->assertStatus(204);

        $this->assertDatabaseMissing('program_levels', ['id' => $level->id]);
    }

    public function test_level_of_another_program_is_404(): void
    {
        $other = Program::create(['name' => 'Lain', 'tipe' => 'online', 'price' => 1, 'is_active' => true]);
        $foreign = $other->levels()->create(['name' => 'Milik Lain', 'sort_order' => 1]);
        $this->asManagement();

        $this->putJson($this->url("/{$foreign->id}"), ['name' => 'Dibajak'])->assertStatus(404);
        $this->deleteJson($this->url("/{$foreign->id}"))->assertStatus(404);

        $this->assertSame('Milik Lain', ProgramLevel::find($foreign->id)->name);
    }

    public function test_deleting_unused_program_removes_its_levels(): void
    {
        $this->program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
        $this->asManagement();

        $this->deleteJson("/api/programs/{$this->program->id}")->assertStatus(204);

        $this->assertDatabaseCount('program_levels', 0);
    }
}
