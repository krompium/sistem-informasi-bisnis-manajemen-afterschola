<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskUpdateTest extends TestCase
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

    /** Developer anggota team, proyek di team itu, dan satu task yang di-assign ke developer tsb. */
    private function developerWithTask(array $taskAttributes = []): array
    {
        $dev = $this->userWithRole('developer');
        $team = Team::factory()->create();
        $team->members()->attach($dev->id, ['role_in_team' => 'member']);
        $project = Project::factory()->create(['team_id' => $team->id]);
        $task = Task::factory()->create(array_merge(
            ['project_id' => $project->id, 'assignee_id' => $dev->id],
            $taskAttributes,
        ));

        return [$dev, $task];
    }

    // ---- Log otomatis saat task diubah ----

    public function test_changing_status_and_progress_writes_log_entries(): void
    {
        [$dev, $task] = $this->developerWithTask(['status' => 'todo', 'progress' => 0]);

        Sanctum::actingAs($dev);

        $this->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress', 'progress' => 40])->assertOk();

        $this->assertDatabaseCount('task_updates', 2);
        $this->assertDatabaseHas('task_updates', [
            'task_id' => $task->id, 'user_id' => $dev->id, 'note' => 'Status: todo → in_progress',
        ]);
        $this->assertDatabaseHas('task_updates', [
            'task_id' => $task->id, 'user_id' => $dev->id, 'note' => 'Progress: 0% → 40%',
        ]);
    }

    public function test_reassigning_a_task_logs_old_and_new_assignee_names(): void
    {
        [$dev, $task] = $this->developerWithTask();
        $teammate = $this->userWithRole('developer');
        $task->project->team->members()->attach($teammate->id, ['role_in_team' => 'member']);

        Sanctum::actingAs($dev);

        $this->putJson("/api/tasks/{$task->id}", ['assignee_id' => $teammate->id])->assertOk();

        $this->assertDatabaseHas('task_updates', [
            'task_id' => $task->id,
            'user_id' => $dev->id,
            'note' => "Assignee: {$dev->name} → {$teammate->name}",
        ]);
    }

    public function test_update_without_tracked_change_writes_no_log(): void
    {
        [$dev, $task] = $this->developerWithTask(['status' => 'todo', 'progress' => 0]);

        Sanctum::actingAs($dev);

        // Nilai sama + field yang tidak dilacak (judul) -> tidak ada entri.
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'todo', 'progress' => 0, 'title' => 'Judul baru'])
            ->assertOk();

        $this->assertDatabaseCount('task_updates', 0);
    }

    // ---- Membaca log ----

    public function test_developer_lists_log_newest_first_with_author_name(): void
    {
        [$dev, $task] = $this->developerWithTask();
        TaskUpdate::factory()->create(['task_id' => $task->id, 'user_id' => $dev->id, 'note' => 'lama', 'created_at' => now()->subHour()]);
        TaskUpdate::factory()->create(['task_id' => $task->id, 'user_id' => $dev->id, 'note' => 'baru', 'created_at' => now()]);

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks/{$task->id}/updates")
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.note', 'baru')
            ->assertJsonPath('data.0.user.name', $dev->name)
            ->assertJsonPath('data.1.note', 'lama');
    }

    public function test_developer_gets_404_for_other_team_task_log(): void
    {
        [$dev, $own] = $this->developerWithTask();
        $other = Task::factory()->create();

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks/{$own->id}/updates")->assertOk(); // jaga-jaga: route memang ada
        $this->getJson("/api/tasks/{$other->id}/updates")->assertNotFound();
        // Otorisasi sebelum validasi: body kosong tetap 404, bukan 422.
        $this->postJson("/api/tasks/{$other->id}/updates", [])->assertNotFound();
    }

    public function test_management_can_read_but_not_comment(): void
    {
        $task = Task::factory()->create();
        TaskUpdate::factory()->create(['task_id' => $task->id]);

        Sanctum::actingAs($this->userWithRole('management'));

        $this->getJson("/api/tasks/{$task->id}/updates")->assertOk()->assertJsonCount(1, 'data');
        // Otorisasi sebelum validasi: body kosong tetap 403, bukan 422.
        $this->postJson("/api/tasks/{$task->id}/updates", [])->assertForbidden();
        $this->postJson("/api/tasks/{$task->id}/updates", ['note' => 'Halo'])->assertForbidden();
        $this->assertDatabaseCount('task_updates', 1);
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void
    {
        $task = Task::factory()->create();

        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson("/api/tasks/{$task->id}/updates")->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }

    // ---- Komentar manual ----

    public function test_developer_can_post_a_comment(): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/updates", ['note' => 'PR sudah dibuka, mohon review.'])
            ->assertCreated()
            ->assertJsonPath('data.note', 'PR sudah dibuka, mohon review.')
            ->assertJsonPath('data.user.id', $dev->id);

        $this->assertDatabaseHas('task_updates', [
            'task_id' => $task->id, 'user_id' => $dev->id, 'note' => 'PR sudah dibuka, mohon review.',
        ]);
    }

    #[DataProvider('invalidNotes')]
    public function test_comment_validation(string $note): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/updates", ['note' => $note])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
    }

    public static function invalidNotes(): array
    {
        return [
            'note kosong' => [''],
            'note lebih dari 2000 karakter' => [str_repeat('a', 2001)],
        ];
    }

    // ---- Siklus hidup ----

    public function test_deleting_a_task_deletes_its_log(): void
    {
        [$dev, $task] = $this->developerWithTask();
        TaskUpdate::factory()->count(2)->create(['task_id' => $task->id, 'user_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertDatabaseCount('task_updates', 0);
    }

    public function test_log_survives_deleting_its_author(): void // UserController::destroy menghapus permanen
    {
        [$dev, $task] = $this->developerWithTask();
        $author = User::factory()->create();
        $entry = TaskUpdate::factory()->create(['task_id' => $task->id, 'user_id' => $author->id]);

        $author->delete();

        $this->assertDatabaseHas('task_updates', ['id' => $entry->id, 'user_id' => null]);

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks/{$task->id}/updates")
            ->assertOk()
            ->assertJsonPath('data.0.user', null);
    }
}
