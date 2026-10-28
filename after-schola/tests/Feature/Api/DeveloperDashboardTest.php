<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeveloperDashboardTest extends TestCase
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

    /** Developer yang menjadi anggota satu team. */
    private function developerWithTeam(): array
    {
        $dev = $this->userWithRole('developer');
        $team = Team::factory()->create();
        $team->members()->attach($dev->id, ['role_in_team' => 'member']);

        return [$dev, $team];
    }

    public function test_developer_gets_developer_payload_without_management_totals(): void
    {
        [$dev] = $this->developerWithTeam();

        Sanctum::actingAs($dev);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('role', 'developer')
            ->assertJsonStructure([
                'role',
                'ongoing_projects',
                'tasks_by_status' => ['todo', 'in_progress', 'review', 'done'],
                'workload',
            ])
            ->assertJsonMissingPath('totals'); // statistik lintas sekolah milik Management
    }

    public function test_dashboard_lists_only_ongoing_projects_of_own_team(): void
    {
        [$dev, $team] = $this->developerWithTeam();
        $ongoing = Project::factory()->create(['team_id' => $team->id, 'status' => 'ongoing']);
        Project::factory()->create(['team_id' => $team->id, 'status' => 'planning']);
        Project::factory()->create(['team_id' => $team->id, 'status' => 'done']);
        Project::factory()->create(['status' => 'ongoing']); // team lain

        Sanctum::actingAs($dev);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'ongoing_projects')
            ->assertJsonPath('ongoing_projects.0.id', $ongoing->id)
            ->assertJsonPath('ongoing_projects.0.name', $ongoing->name);
    }

    public function test_dashboard_counts_tasks_by_status_and_workload_per_assignee(): void
    {
        [$dev, $team] = $this->developerWithTeam();
        $mate = $this->userWithRole('developer');
        $team->members()->attach($mate->id, ['role_in_team' => 'member']);
        $project = Project::factory()->create(['team_id' => $team->id, 'status' => 'ongoing']);

        $make = fn (User $user, string $status) => Task::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'status' => $status,
        ]);
        $make($dev, 'todo');
        $make($dev, 'in_progress');
        $make($mate, 'todo');
        $make($mate, 'done');
        Task::factory()->create(); // team lain: tidak ikut dihitung

        Sanctum::actingAs($dev);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('tasks_by_status.todo', 2)
            ->assertJsonPath('tasks_by_status.in_progress', 1)
            ->assertJsonPath('tasks_by_status.review', 0)
            ->assertJsonPath('tasks_by_status.done', 1)
            ->assertJsonPath('ongoing_projects.0.tasks_total', 4)
            ->assertJsonPath('ongoing_projects.0.tasks_done', 1)
            // workload = task belum done per assignee, terbanyak lebih dulu
            ->assertJsonCount(2, 'workload')
            ->assertJsonPath('workload.0.user_id', $dev->id)
            ->assertJsonPath('workload.0.name', $dev->name)
            ->assertJsonPath('workload.0.open_tasks', 2)
            ->assertJsonPath('workload.1.user_id', $mate->id)
            ->assertJsonPath('workload.1.open_tasks', 1);
    }

    public function test_developer_without_team_gets_empty_dashboard(): void
    {
        Task::factory()->create(); // data team lain tidak boleh bocor

        Sanctum::actingAs($this->userWithRole('developer'));

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('role', 'developer')
            ->assertJsonPath('ongoing_projects', [])
            ->assertJsonPath('workload', [])
            ->assertJsonPath('tasks_by_status.todo', 0)
            ->assertJsonPath('tasks_by_status.in_progress', 0)
            ->assertJsonPath('tasks_by_status.review', 0)
            ->assertJsonPath('tasks_by_status.done', 0);
    }
}
