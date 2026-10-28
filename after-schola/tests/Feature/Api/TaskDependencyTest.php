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

class TaskDependencyTest extends TestCase
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

    /** Developer anggota team dan satu proyek di team itu. */
    private function developerWithProject(): array
    {
        $dev = $this->userWithRole('developer');
        $team = Team::factory()->create();
        $team->members()->attach($dev->id, ['role_in_team' => 'member']);

        return [$dev, Project::factory()->create(['team_id' => $team->id])];
    }

    private function taskIn(Project $project, User $dev, array $attributes = []): Task
    {
        return Task::factory()->create(array_merge(['project_id' => $project->id, 'assignee_id' => $dev->id], $attributes));
    }

    // ---- Menambah dan membaca ----

    public function test_developer_adds_a_dependency_and_lists_it(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = $this->taskIn($project, $dev);
        $prereq = $this->taskIn($project, $dev, ['title' => 'Rancang skema']);

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/dependencies", ['depends_on_id' => $prereq->id])
            ->assertCreated()
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.depends_on_id', $prereq->id)
            ->assertJsonPath('data.prerequisite.title', 'Rancang skema');

        $this->getJson("/api/tasks/{$task->id}/dependencies")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.prerequisite.status', 'todo');
    }

    public function test_task_exposes_how_many_unfinished_prerequisites_block_it(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = $this->taskIn($project, $dev);
        $prereq = $this->taskIn($project, $dev, ['status' => 'in_progress']);
        TaskDependency::factory()->create(['task_id' => $task->id, 'depends_on_id' => $prereq->id]);

        Sanctum::actingAs($dev);

        $blocked = fn () => collect($this->getJson("/api/tasks?project_id={$project->id}")->assertOk()->json('data'))
            ->firstWhere('id', $task->id)['blocked_count'];

        $this->assertSame(1, $blocked());

        $prereq->update(['status' => 'done']);

        $this->assertSame(0, $blocked());
    }

    // ---- Aturan ----

    public function test_duplicate_self_and_cross_project_dependencies_are_rejected(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = $this->taskIn($project, $dev);
        $prereq = $this->taskIn($project, $dev);
        $otherProject = Project::factory()->create(['team_id' => $project->team_id]);
        $elsewhere = $this->taskIn($otherProject, $dev);
        TaskDependency::factory()->create(['task_id' => $task->id, 'depends_on_id' => $prereq->id]);

        Sanctum::actingAs($dev);

        foreach ([$prereq->id, $task->id, $elsewhere->id] as $invalid) { // duplikat, dirinya sendiri, proyek lain
            $this->postJson("/api/tasks/{$task->id}/dependencies", ['depends_on_id' => $invalid])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('depends_on_id');
        }

        $this->assertDatabaseCount('task_dependencies', 1);
    }

    public function test_circular_dependencies_are_rejected_directly_and_transitively(): void
    {
        [$dev, $project] = $this->developerWithProject();
        [$a, $b, $c] = [$this->taskIn($project, $dev), $this->taskIn($project, $dev), $this->taskIn($project, $dev)];
        TaskDependency::factory()->create(['task_id' => $a->id, 'depends_on_id' => $b->id]); // A bergantung pada B
        TaskDependency::factory()->create(['task_id' => $b->id, 'depends_on_id' => $c->id]); // B bergantung pada C

        Sanctum::actingAs($dev);

        // Langsung: B bergantung pada A (A sudah bergantung pada B).
        $this->postJson("/api/tasks/{$b->id}/dependencies", ['depends_on_id' => $a->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('depends_on_id');
        // Berantai: C bergantung pada A (A -> B -> C).
        $this->postJson("/api/tasks/{$c->id}/dependencies", ['depends_on_id' => $a->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('depends_on_id');
        // Arah searah tetap boleh: A bergantung pada C.
        $this->postJson("/api/tasks/{$a->id}/dependencies", ['depends_on_id' => $c->id])->assertCreated();

        $this->assertDatabaseCount('task_dependencies', 3);
    }

    public function test_depends_on_id_is_required(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = $this->taskIn($project, $dev);

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/dependencies", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('depends_on_id');
    }

    // ---- Hapus ----

    public function test_developer_removes_a_dependency_but_gets_404_for_other_team(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $edge = TaskDependency::factory()->create([
            'task_id' => $this->taskIn($project, $dev)->id,
            'depends_on_id' => $this->taskIn($project, $dev)->id,
        ]);
        $otherEdge = TaskDependency::factory()->create();

        Sanctum::actingAs($dev);

        $this->deleteJson("/api/dependencies/{$edge->id}")->assertNoContent();
        $this->assertDatabaseMissing('task_dependencies', ['id' => $edge->id]);

        $this->deleteJson("/api/dependencies/{$otherEdge->id}")->assertNotFound();
        $this->assertDatabaseHas('task_dependencies', ['id' => $otherEdge->id]);
    }

    public function test_deleting_a_task_deletes_its_dependency_edges_in_both_directions(): void
    {
        [$dev, $project] = $this->developerWithProject();
        [$a, $b, $c] = [$this->taskIn($project, $dev), $this->taskIn($project, $dev), $this->taskIn($project, $dev)];
        TaskDependency::factory()->create(['task_id' => $a->id, 'depends_on_id' => $b->id]);
        TaskDependency::factory()->create(['task_id' => $b->id, 'depends_on_id' => $c->id]);

        Sanctum::actingAs($dev);

        $this->deleteJson("/api/tasks/{$b->id}")->assertNoContent(); // B: prasyarat milik A sekaligus bergantung pada C

        $this->assertDatabaseCount('task_dependencies', 0);
    }

    // ---- Akses ----

    public function test_developer_gets_404_for_other_team_task_dependencies(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $own = $this->taskIn($project, $dev);
        $other = Task::factory()->create();

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks/{$own->id}/dependencies")->assertOk(); // jaga-jaga: route memang ada
        $this->getJson("/api/tasks/{$other->id}/dependencies")->assertNotFound();
        // Otorisasi sebelum validasi: body kosong tetap 404, bukan 422.
        $this->postJson("/api/tasks/{$other->id}/dependencies", [])->assertNotFound();
    }

    public function test_management_can_read_but_not_write_dependencies(): void
    {
        $edge = TaskDependency::factory()->create();

        Sanctum::actingAs($this->userWithRole('management'));

        $this->getJson("/api/tasks/{$edge->task_id}/dependencies")->assertOk()->assertJsonCount(1, 'data');
        // Otorisasi sebelum validasi: body kosong tetap 403, bukan 422.
        $this->postJson("/api/tasks/{$edge->task_id}/dependencies", [])->assertForbidden();
        $this->deleteJson("/api/dependencies/{$edge->id}")->assertForbidden();
        $this->assertDatabaseCount('task_dependencies', 1);
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void
    {
        $task = Task::factory()->create();

        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson("/api/tasks/{$task->id}/dependencies")->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }
}
