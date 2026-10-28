<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectGanttTest extends TestCase
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

    private function developerWithProject(): array
    {
        $dev = $this->userWithRole('developer');
        $team = Team::factory()->create();
        $team->members()->attach($dev->id, ['role_in_team' => 'member']);

        return [$dev, Project::factory()->create(['team_id' => $team->id, 'name' => 'API Absensi'])];
    }

    public function test_developer_gets_project_tasks_ordered_by_start_with_dependencies(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $late = Task::factory()->create([
            'project_id' => $project->id, 'assignee_id' => $dev->id,
            'start_date' => '2026-11-10', 'due_date' => '2026-11-12',
        ]);
        $early = Task::factory()->create([ // tanpa tanggal mulai -> diurutkan menurut tenggat
            'project_id' => $project->id, 'assignee_id' => $dev->id,
            'start_date' => null, 'due_date' => '2026-11-05',
        ]);
        $sibling = Project::factory()->create(['team_id' => $project->team_id]);
        Task::factory()->create(['project_id' => $sibling->id, 'assignee_id' => $dev->id]); // proyek lain
        $edge = TaskDependency::factory()->create(['task_id' => $late->id, 'depends_on_id' => $early->id]);

        Sanctum::actingAs($dev);

        $this->getJson("/api/projects/{$project->id}/gantt")
            ->assertOk()
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.project.name', 'API Absensi')
            ->assertJsonCount(2, 'data.tasks')
            ->assertJsonPath('data.tasks.0.id', $early->id)
            ->assertJsonPath('data.tasks.0.start_date', null)
            ->assertJsonPath('data.tasks.1.id', $late->id)
            ->assertJsonPath('data.tasks.1.start_date', '2026-11-10')
            ->assertJsonPath('data.tasks.1.assignee.name', $dev->name)
            ->assertJsonCount(1, 'data.dependencies')
            ->assertJsonPath('data.dependencies.0.id', $edge->id)
            ->assertJsonPath('data.dependencies.0.task_id', $late->id)
            ->assertJsonPath('data.dependencies.0.depends_on_id', $early->id);
    }

    public function test_developer_gets_404_for_other_team_gantt(): void
    {
        [$dev, $own] = $this->developerWithProject();
        $other = Project::factory()->create();

        Sanctum::actingAs($dev);

        $this->getJson("/api/projects/{$own->id}/gantt")->assertOk(); // jaga-jaga: route memang ada
        $this->getJson("/api/projects/{$other->id}/gantt")->assertNotFound();
    }

    public function test_management_can_view_any_project_gantt(): void
    {
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs($this->userWithRole('management'));

        $this->getJson("/api/projects/{$project->id}/gantt")
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks');
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void
    {
        $project = Project::factory()->create();

        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson("/api/projects/{$project->id}/gantt")->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }
}
