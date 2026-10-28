<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function teamWith(User $member): Team
    {
        $team = Team::factory()->create();
        $team->members()->attach($member->id, ['role_in_team' => 'member']);

        return $team;
    }

    private function validPayload(Team $team): array
    {
        return [
            'team_id' => $team->id,
            'name' => 'Proyek A',
            'description' => 'Deskripsi',
            'start_date' => '2026-11-01',
            'due_date' => '2026-12-01',
        ];
    }

    // ---- Developer: baca (hanya team sendiri) ----

    public function test_developer_index_lists_only_own_team_projects(): void // T1/T3
    {
        $dev = $this->userWithRole('developer');
        $ownTeam = $this->teamWith($dev);
        $own = Project::factory()->create(['team_id' => $ownTeam->id]);
        Project::factory()->create(); // team lain

        Sanctum::actingAs($dev);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.start_date', now()->toDateString());
    }

    public function test_developer_gets_404_for_other_team_project(): void // T3
    {
        $dev = $this->userWithRole('developer');
        $own = Project::factory()->create(['team_id' => $this->teamWith($dev)->id]);
        $other = Project::factory()->create();

        Sanctum::actingAs($dev);

        $this->getJson("/api/projects/{$own->id}")->assertOk(); // jaga-jaga: route memang ada
        $this->getJson("/api/projects/{$other->id}")->assertNotFound();
    }

    // ---- Management: read-only ----

    public function test_management_sees_all_projects_and_can_filter_by_team(): void // T4
    {
        $a = Project::factory()->create();
        Project::factory()->create();

        Sanctum::actingAs($this->userWithRole('management'));

        $this->getJson('/api/projects')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/projects/{$a->id}")->assertOk();
        $this->getJson("/api/projects?team_id={$a->team_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a->id);
    }

    public function test_management_cannot_write_projects(): void // T5
    {
        $project = Project::factory()->create();

        Sanctum::actingAs($this->userWithRole('management'));

        // Otorisasi sebelum validasi: body kosong tetap 403, bukan 422.
        $this->postJson('/api/projects', [])->assertForbidden();
        $this->putJson("/api/projects/{$project->id}", ['status' => 'done'])->assertForbidden();
        $this->deleteJson("/api/projects/{$project->id}")->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'planning']);
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void // T6
    {
        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson('/api/projects')->assertForbidden();
        $this->postJson('/api/projects', [])->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }

    // ---- Developer: kelola proyek di team sendiri ----

    public function test_developer_can_create_project_in_own_team_with_default_status(): void
    {
        $dev = $this->userWithRole('developer');
        $team = $this->teamWith($dev);

        Sanctum::actingAs($dev);

        $this->postJson('/api/projects', $this->validPayload($team))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Proyek A')
            ->assertJsonPath('data.status', 'planning');

        $this->assertDatabaseHas('projects', ['team_id' => $team->id, 'name' => 'Proyek A']);
    }

    public function test_developer_cannot_create_project_in_other_team(): void // pola create: tdd.md §4
    {
        $dev = $this->userWithRole('developer');
        $this->teamWith($dev);
        $other = Team::factory()->create();

        Sanctum::actingAs($dev);

        $this->postJson('/api/projects', $this->validPayload($other))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_developer_can_update_own_project_but_gets_404_on_other_team(): void
    {
        $dev = $this->userWithRole('developer');
        $own = Project::factory()->create(['team_id' => $this->teamWith($dev)->id]);
        $other = Project::factory()->create();

        Sanctum::actingAs($dev);

        $this->putJson("/api/projects/{$own->id}", ['status' => 'ongoing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ongoing');

        // Body invalid dari non-anggota tetap 404 (otorisasi sebelum validasi).
        $this->putJson("/api/projects/{$other->id}", ['status' => 'bogus'])->assertNotFound();
        $this->assertDatabaseHas('projects', ['id' => $other->id, 'status' => 'planning']);
    }

    public function test_developer_can_delete_own_project_but_gets_404_on_other_team(): void
    {
        $dev = $this->userWithRole('developer');
        $own = Project::factory()->create(['team_id' => $this->teamWith($dev)->id]);
        $other = Project::factory()->create();

        Sanctum::actingAs($dev);

        $this->deleteJson("/api/projects/{$own->id}")->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $own->id]);

        $this->deleteJson("/api/projects/{$other->id}")->assertNotFound();
        $this->assertDatabaseHas('projects', ['id' => $other->id]);
    }

    public function test_update_project_keeps_due_date_after_start_date(): void
    {
        $dev = $this->userWithRole('developer');
        $project = Project::factory()->create([
            'team_id' => $this->teamWith($dev)->id,
            'start_date' => '2026-11-01',
            'due_date' => '2026-12-01',
        ]);

        Sanctum::actingAs($dev);

        $this->putJson("/api/projects/{$project->id}", ['due_date' => '2026-10-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');
        $this->putJson("/api/projects/{$project->id}", ['start_date' => '2026-12-15'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_project_with_tasks_cannot_be_deleted(): void // Q5
    {
        $dev = $this->userWithRole('developer');
        $project = Project::factory()->create(['team_id' => $this->teamWith($dev)->id]);
        Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->deleteJson("/api/projects/{$project->id}")->assertUnprocessable();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    // ---- Validasi (satu representatif per field) ----

    #[DataProvider('invalidProjectPayloads')]
    public function test_create_project_validation(array $override, string $errorKey): void // T8 (bagian proyek)
    {
        $dev = $this->userWithRole('developer');
        $team = $this->teamWith($dev);

        Sanctum::actingAs($dev);

        $this->postJson('/api/projects', array_merge($this->validPayload($team), $override))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);
    }

    public static function invalidProjectPayloads(): array
    {
        return [
            'name wajib' => [['name' => ''], 'name'],
            'status di luar enum' => [['status' => 'archived'], 'status'],
            'start_date wajib' => [['start_date' => null], 'start_date'],
            'due_date wajib' => [['due_date' => null], 'due_date'],
            'due_date sebelum start_date' => [['start_date' => '2026-11-01', 'due_date' => '2026-10-01'], 'due_date'],
        ];
    }
}
