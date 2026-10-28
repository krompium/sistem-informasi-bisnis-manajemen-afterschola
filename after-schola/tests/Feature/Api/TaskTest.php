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

class TaskTest extends TestCase
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

    /** Developer yang menjadi anggota team, beserta satu proyek di team itu. */
    private function developerWithProject(): array
    {
        $dev = $this->userWithRole('developer');
        $team = Team::factory()->create();
        $team->members()->attach($dev->id, ['role_in_team' => 'member']);

        return [$dev, Project::factory()->create(['team_id' => $team->id])];
    }

    private function validPayload(Project $project, User $assignee): array
    {
        return [
            'project_id' => $project->id,
            'title' => 'Buat endpoint login',
            'description' => 'Detail task',
            'assignee_id' => $assignee->id,
            'due_date' => '2026-11-20',
        ];
    }

    // ---- Developer: baca (hanya team sendiri) ----

    public function test_developer_index_lists_only_own_team_tasks_and_filters_by_project(): void // T1/T3
    {
        [$dev, $projectA] = $this->developerWithProject();
        $projectB = Project::factory()->create(['team_id' => $projectA->team_id]);
        $a = Task::factory()->create(['project_id' => $projectA->id, 'assignee_id' => $dev->id]);
        Task::factory()->create(['project_id' => $projectB->id, 'assignee_id' => $dev->id]);
        Task::factory()->create(); // team lain

        Sanctum::actingAs($dev);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/tasks?project_id={$projectA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a->id);
    }

    public function test_developer_gets_404_for_other_team_task(): void // T3
    {
        [$dev, $project] = $this->developerWithProject();
        $own = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);
        $other = Task::factory()->create();

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks/{$own->id}")->assertOk(); // jaga-jaga: route memang ada
        $this->getJson("/api/tasks/{$other->id}")->assertNotFound();
    }

    // ---- Management: read-only ----

    public function test_management_sees_all_tasks(): void // T4
    {
        $a = Task::factory()->create();
        Task::factory()->create();

        Sanctum::actingAs($this->userWithRole('management'));

        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/tasks/{$a->id}")->assertOk();
    }

    public function test_management_cannot_write_tasks(): void // T5
    {
        $task = Task::factory()->create();

        Sanctum::actingAs($this->userWithRole('management'));

        // Otorisasi sebelum validasi: body kosong tetap 403, bukan 422.
        $this->postJson('/api/tasks', [])->assertForbidden();
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'done'])->assertForbidden();
        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void // T6
    {
        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson('/api/tasks')->assertForbidden();
        $this->postJson('/api/tasks', [])->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }

    // ---- Developer: kelola task di team sendiri ----

    public function test_developer_can_create_task_with_default_status_and_progress(): void
    {
        [$dev, $project] = $this->developerWithProject();

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', $this->validPayload($project, $dev))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Buat endpoint login')
            ->assertJsonPath('data.assignee_id', $dev->id)
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.progress', 0);

        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'assignee_id' => $dev->id]);
    }

    public function test_developer_cannot_create_task_in_other_team_project(): void // pola create: tdd.md §4
    {
        [$dev] = $this->developerWithProject();
        $otherProject = Project::factory()->create();

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', $this->validPayload($otherProject, $dev))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_assignee_must_be_member_of_project_team(): void // T7
    {
        [$dev, $project] = $this->developerWithProject();
        $outsider = $this->userWithRole('developer'); // bukan anggota team proyek
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', $this->validPayload($project, $outsider))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');

        $this->putJson("/api/tasks/{$task->id}", ['assignee_id' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assignee_id' => $dev->id]);
    }

    public function test_developer_can_update_status_progress_and_reassign_to_team_member(): void // T9
    {
        [$dev, $project] = $this->developerWithProject();
        $teammate = $this->userWithRole('developer');
        $project->team->members()->attach($teammate->id, ['role_in_team' => 'member']);
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->putJson("/api/tasks/{$task->id}", [
            'status' => 'in_progress',
            'progress' => 40,
            'assignee_id' => $teammate->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.progress', 40);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
            'progress' => 40,
            'assignee_id' => $teammate->id,
        ]);
    }

    public function test_developer_gets_404_on_update_and_delete_for_other_team_task(): void
    {
        [$dev] = $this->developerWithProject();
        $other = Task::factory()->create();

        Sanctum::actingAs($dev);

        // Body invalid dari non-anggota tetap 404 (otorisasi sebelum validasi).
        $this->putJson("/api/tasks/{$other->id}", ['status' => 'bogus'])->assertNotFound();
        $this->deleteJson("/api/tasks/{$other->id}")->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $other->id, 'status' => 'todo']);
    }

    public function test_developer_can_delete_own_task(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    // ---- Kebutuhan board (frontend): nama assignee + ukuran halaman ----

    public function test_task_includes_assignee_name_for_the_board(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks?project_id={$project->id}")
            ->assertOk()
            ->assertJsonPath('data.0.assignee.name', $dev->name);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $dev->id)
            ->assertJsonPath('data.assignee.name', $dev->name);
    }

    public function test_index_honours_per_page_with_a_cap_of_100(): void
    {
        [$dev, $project] = $this->developerWithProject();
        Task::factory()->count(3)->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->getJson('/api/tasks?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);

        $this->getJson('/api/tasks?per_page=1000')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 100);
    }

    // ---- Tautan GitHub (opsional, diisi manual) ----

    public function test_developer_can_attach_github_url_when_creating_task(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $url = 'https://github.com/krompium/sistem-informasi-bisnis-manajemen-afterschola/pull/12';

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', array_merge($this->validPayload($project, $dev), ['github_url' => $url]))
            ->assertCreated()
            ->assertJsonPath('data.github_url', $url);

        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'github_url' => $url]);
    }

    #[DataProvider('invalidGithubUrls')]
    public function test_github_url_must_be_an_https_github_link(string $url): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', array_merge($this->validPayload($project, $dev), ['github_url' => $url]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('github_url');

        $this->putJson("/api/tasks/{$task->id}", ['github_url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('github_url');
    }

    public static function invalidGithubUrls(): array
    {
        return [
            'domain lain' => ['https://example.com/pull/1'],
            'skema javascript' => ['javascript:alert(1)'],
            'http bukan https' => ['http://github.com/o/r/pull/1'],
        ];
    }

    public function test_github_url_can_be_cleared_on_update(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $dev->id,
            'github_url' => 'https://github.com/o/r/pull/1',
        ]);

        Sanctum::actingAs($dev);

        $this->putJson("/api/tasks/{$task->id}", ['github_url' => null])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'github_url' => null]);
    }

    // ---- Prioritas ----

    public function test_priority_defaults_to_medium_and_can_be_set_on_create(): void
    {
        [$dev, $project] = $this->developerWithProject();

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', $this->validPayload($project, $dev))
            ->assertCreated()
            ->assertJsonPath('data.priority', 'medium');

        $this->postJson('/api/tasks', array_merge($this->validPayload($project, $dev), ['priority' => 'high']))
            ->assertCreated()
            ->assertJsonPath('data.priority', 'high');
    }

    public function test_priority_can_be_changed_on_update(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->putJson("/api/tasks/{$task->id}", ['priority' => 'low'])
            ->assertOk()
            ->assertJsonPath('data.priority', 'low');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'priority' => 'low']);
    }

    public function test_index_can_filter_by_priority(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $high = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id, 'priority' => 'high']);
        Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id, 'priority' => 'low']);
        Task::factory()->create(['priority' => 'high']); // team lain

        Sanctum::actingAs($dev);

        $this->getJson('/api/tasks?priority=high')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $high->id);
    }

    // ---- Tanggal mulai (opsional, untuk Gantt) ----

    public function test_start_date_is_optional_and_returned(): void
    {
        [$dev, $project] = $this->developerWithProject();

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', $this->validPayload($project, $dev))
            ->assertCreated()
            ->assertJsonPath('data.start_date', null);

        $this->postJson('/api/tasks', array_merge($this->validPayload($project, $dev), ['start_date' => '2026-11-10']))
            ->assertCreated()
            ->assertJsonPath('data.start_date', '2026-11-10');
    }

    public function test_update_keeps_start_date_on_or_before_due_date(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $dev->id,
            'start_date' => '2026-11-10',
            'due_date' => '2026-11-20',
        ]);

        Sanctum::actingAs($dev);

        $this->putJson("/api/tasks/{$task->id}", ['start_date' => '2026-11-25'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
        $this->putJson("/api/tasks/{$task->id}", ['due_date' => '2026-11-05'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');

        // Tanggal mulai boleh dikosongkan, dan tenggat boleh mundur bila tidak ada tanggal mulai.
        $this->putJson("/api/tasks/{$task->id}", ['start_date' => null])->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'start_date' => null]);
        $this->putJson("/api/tasks/{$task->id}", ['due_date' => '2026-11-05'])->assertOk();
    }

    // ---- Kategori (daftar tetap) ----

    public function test_category_defaults_to_feature_and_can_be_set_and_changed(): void
    {
        [$dev, $project] = $this->developerWithProject();

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', $this->validPayload($project, $dev))
            ->assertCreated()
            ->assertJsonPath('data.category', 'feature');

        $created = $this->postJson('/api/tasks', array_merge($this->validPayload($project, $dev), ['category' => 'bug']))
            ->assertCreated()
            ->assertJsonPath('data.category', 'bug');

        $this->putJson('/api/tasks/'.$created->json('data.id'), ['category' => 'docs'])
            ->assertOk()
            ->assertJsonPath('data.category', 'docs');
    }

    public function test_index_can_filter_by_category(): void
    {
        [$dev, $project] = $this->developerWithProject();
        $bug = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id, 'category' => 'bug']);
        Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id, 'category' => 'docs']);
        Task::factory()->create(['category' => 'bug']); // team lain

        Sanctum::actingAs($dev);

        $this->getJson('/api/tasks?category=bug')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bug->id);
    }

    // ---- Rentang tenggat lintas proyek (untuk kalender) ----

    public function test_index_can_filter_by_due_date_range_across_projects(): void
    {
        [$dev, $projectA] = $this->developerWithProject();
        $projectB = Project::factory()->create(['team_id' => $projectA->team_id]);
        $lower = Task::factory()->create(['project_id' => $projectA->id, 'assignee_id' => $dev->id, 'due_date' => '2026-11-01']);
        $upper = Task::factory()->create(['project_id' => $projectB->id, 'assignee_id' => $dev->id, 'due_date' => '2026-11-30']);
        Task::factory()->create(['project_id' => $projectA->id, 'assignee_id' => $dev->id, 'due_date' => '2026-10-31']);
        Task::factory()->create(['project_id' => $projectB->id, 'assignee_id' => $dev->id, 'due_date' => '2026-12-01']);
        Task::factory()->create(['due_date' => '2026-11-15']); // team lain, dalam rentang

        Sanctum::actingAs($dev);

        $ids = collect($this->getJson('/api/tasks?due_from=2026-11-01&due_to=2026-11-30')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data'))->pluck('id')->sort()->values()->all();

        // Batas bawah dan atas sama-sama termasuk.
        $this->assertSame(collect([$lower->id, $upper->id])->sort()->values()->all(), $ids);
    }

    #[DataProvider('invalidIndexFilters')]
    public function test_index_filters_validate_their_input(string $query, string $errorKey): void
    {
        [$dev] = $this->developerWithProject();

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);
    }

    public static function invalidIndexFilters(): array
    {
        return [
            'due_from bukan tanggal' => ['due_from=bukan-tanggal', 'due_from'],
            'due_to sebelum due_from' => ['due_from=2026-11-10&due_to=2026-11-01', 'due_to'],
            'priority di luar enum' => ['priority=urgent', 'priority'],
            'category di luar enum' => ['category=sampah', 'category'],
        ];
    }

    // ---- Validasi (satu representatif per field; progress dites di kedua batas) ----

    #[DataProvider('invalidTaskPayloads')]
    public function test_create_task_validation(array $override, string $errorKey): void // T8
    {
        [$dev, $project] = $this->developerWithProject();

        Sanctum::actingAs($dev);

        $this->postJson('/api/tasks', array_merge($this->validPayload($project, $dev), $override))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);
    }

    public static function invalidTaskPayloads(): array
    {
        return [
            'title wajib' => [['title' => ''], 'title'],
            'assignee_id wajib' => [['assignee_id' => null], 'assignee_id'],
            'due_date wajib' => [['due_date' => null], 'due_date'],
            'status di luar enum' => [['status' => 'archived'], 'status'],
            'progress di atas 100' => [['progress' => 101], 'progress'],
            'progress di bawah 0' => [['progress' => -1], 'progress'],
            'priority di luar enum' => [['priority' => 'urgent'], 'priority'],
            'category di luar enum' => [['category' => 'sampah'], 'category'],
            'start_date setelah due_date' => [['start_date' => '2026-12-01'], 'start_date'],
        ];
    }
}
