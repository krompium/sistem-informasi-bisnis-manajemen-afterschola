<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\RubricCriterion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 1, slice 2: kriteria rubrik penilaian per program (MGT-12).
 */
class RubricCriterionTest extends TestCase
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
        return "/api/programs/{$this->program->id}/rubric-criteria{$suffix}";
    }

    public function test_guest_gets_401_and_roles_without_permission_get_403(): void
    {
        $criterion = $this->program->rubricCriteria()->create(['name' => 'Logika', 'max_score' => 10]);

        $this->getJson($this->url())->assertStatus(401);

        foreach (['trainer', 'finance'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson($this->url())->assertStatus(403);
            $this->postJson($this->url(), ['name' => 'Kreativitas', 'max_score' => 10])->assertStatus(403);
            $this->putJson($this->url("/{$criterion->id}"), ['name' => 'X', 'max_score' => 5])->assertStatus(403);
            $this->deleteJson($this->url("/{$criterion->id}"))->assertStatus(403);
        }

        $this->assertDatabaseCount('rubric_criteria', 1);
    }

    public function test_index_lists_only_this_programs_criteria(): void
    {
        $this->program->rubricCriteria()->create(['name' => 'Logika', 'max_score' => 10]);
        $other = Program::create(['name' => 'Lain', 'tipe' => 'online', 'price' => 1, 'is_active' => true]);
        $other->rubricCriteria()->create(['name' => 'Milik Lain', 'max_score' => 5]);
        $this->asManagement();

        $names = collect($this->getJson($this->url())->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Logika'], $names);
    }

    public function test_program_resource_embeds_rubric_criteria(): void
    {
        $this->program->rubricCriteria()->create(['name' => 'Logika', 'max_score' => 10]);
        $this->asManagement();

        $this->getJson("/api/programs/{$this->program->id}")
            ->assertOk()
            ->assertJsonPath('data.rubric_criteria.0.name', 'Logika')
            ->assertJsonPath('data.rubric_criteria.0.max_score', 10);
    }

    public function test_store_creates_criterion(): void
    {
        $this->asManagement();

        $this->postJson($this->url(), ['name' => 'Logika', 'max_score' => 10])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Logika')
            ->assertJsonPath('data.max_score', 10);

        $this->assertDatabaseHas('rubric_criteria', ['program_id' => $this->program->id, 'name' => 'Logika', 'max_score' => 10]);
    }

    public function test_store_validates_name_and_max_score(): void
    {
        $this->asManagement();

        $this->postJson($this->url(), [])->assertStatus(422)->assertJsonValidationErrors(['name', 'max_score']);
        $this->postJson($this->url(), ['name' => 'X', 'max_score' => 0])->assertStatus(422)->assertJsonValidationErrors('max_score');
        $this->postJson($this->url(), ['name' => 'X', 'max_score' => 'sepuluh'])->assertStatus(422)->assertJsonValidationErrors('max_score');

        $this->assertDatabaseCount('rubric_criteria', 0);
    }

    public function test_update_and_destroy(): void
    {
        $criterion = $this->program->rubricCriteria()->create(['name' => 'Logika', 'max_score' => 10]);
        $this->asManagement();

        $this->putJson($this->url("/{$criterion->id}"), ['name' => 'Logika Berpikir', 'max_score' => 20])
            ->assertOk()
            ->assertJsonPath('data.name', 'Logika Berpikir')
            ->assertJsonPath('data.max_score', 20);

        $this->deleteJson($this->url("/{$criterion->id}"))->assertStatus(204);
        $this->assertDatabaseMissing('rubric_criteria', ['id' => $criterion->id]);
    }

    public function test_criterion_of_another_program_is_404(): void
    {
        $other = Program::create(['name' => 'Lain', 'tipe' => 'online', 'price' => 1, 'is_active' => true]);
        $foreign = $other->rubricCriteria()->create(['name' => 'Milik Lain', 'max_score' => 5]);
        $this->asManagement();

        $this->putJson($this->url("/{$foreign->id}"), ['name' => 'Dibajak', 'max_score' => 1])->assertStatus(404);
        $this->deleteJson($this->url("/{$foreign->id}"))->assertStatus(404);

        $this->assertSame('Milik Lain', RubricCriterion::find($foreign->id)->name);
    }
}
