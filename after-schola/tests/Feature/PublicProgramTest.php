<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 1, slice 3: halaman publik program tanpa login (PUB-01, PUB-02).
 */
class PublicProgramTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    private function makeFullProgram(): Program
    {
        $program = $this->makeProgram(750000);
        $program->levels()->create(['name' => 'Lanjut', 'sort_order' => 2]);
        $program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);
        $program->rubricCriteria()->create(['name' => 'Logika', 'max_score' => 10]);
        $program->jadwalTrial()->create(['hari' => 'senin', 'jam_mulai' => '15:30', 'jam_selesai' => '16:30']);

        return $program;
    }

    public function test_list_is_public_and_returns_only_active_programs_with_levels_and_trial_schedule(): void
    {
        $this->makeFullProgram();
        Program::create(['name' => 'Program Arsip', 'tipe' => 'online', 'price' => 1, 'is_active' => false]);

        $res = $this->getJson('/api/public/programs')->assertOk();

        $this->assertSame(['Coding Kids'], collect($res->json('data'))->pluck('name')->all());
        $res->assertJsonPath('data.0.jenjang', 'SD')
            ->assertJsonPath('data.0.price', '750000.00')
            ->assertJsonPath('data.0.levels.0.name', 'Dasar')
            ->assertJsonPath('data.0.levels.1.name', 'Lanjut')
            ->assertJsonPath('data.0.jadwal_trial.0.hari', 'senin');
    }

    public function test_public_payload_does_not_expose_rubric(): void
    {
        $program = $this->makeFullProgram();

        $this->getJson('/api/public/programs')->assertOk()->assertJsonMissingPath('data.0.rubric_criteria');
        $this->getJson("/api/public/programs/{$program->id}")->assertOk()->assertJsonMissingPath('data.rubric_criteria');
    }

    public function test_detail_of_active_program_is_public(): void
    {
        $program = $this->makeFullProgram();

        $this->getJson("/api/public/programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Coding Kids')
            ->assertJsonPath('data.levels.0.name', 'Dasar');
    }

    public function test_detail_of_inactive_or_missing_program_is_404(): void
    {
        $inactive = Program::create(['name' => 'Arsip', 'tipe' => 'online', 'price' => 1, 'is_active' => false]);

        $this->getJson("/api/public/programs/{$inactive->id}")->assertStatus(404);
        $this->getJson('/api/public/programs/999999')->assertStatus(404);
    }
}
