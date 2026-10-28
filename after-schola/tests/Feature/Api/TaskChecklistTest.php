<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskChecklistTest extends TestCase
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

    /** Developer anggota team, dan satu task di proyek team itu. */
    private function developerWithTask(): array
    {
        $dev = $this->userWithRole('developer');
        $team = Team::factory()->create();
        $team->members()->attach($dev->id, ['role_in_team' => 'member']);
        $project = Project::factory()->create(['team_id' => $team->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        return [$dev, $task];
    }

    public function test_developer_adds_and_lists_items_in_creation_order(): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/checklist", ['title' => 'Tulis test'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Tulis test')
            ->assertJsonPath('data.is_done', false);
        $this->postJson("/api/tasks/{$task->id}/checklist", ['title' => 'Buat endpoint'])->assertCreated();

        $this->getJson("/api/tasks/{$task->id}/checklist")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Tulis test')
            ->assertJsonPath('data.1.title', 'Buat endpoint');
    }

    public function test_developer_can_toggle_and_rename_an_item(): void
    {
        [$dev, $task] = $this->developerWithTask();
        $item = TaskChecklistItem::factory()->create(['task_id' => $task->id]);

        Sanctum::actingAs($dev);

        $this->putJson("/api/checklist/{$item->id}", ['is_done' => true])
            ->assertOk()
            ->assertJsonPath('data.is_done', true);
        $this->putJson("/api/checklist/{$item->id}", ['title' => 'Judul baru'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul baru');

        $this->assertDatabaseHas('task_checklist_items', ['id' => $item->id, 'title' => 'Judul baru', 'is_done' => true]);
    }

    public function test_developer_can_delete_an_item(): void
    {
        [$dev, $task] = $this->developerWithTask();
        $item = TaskChecklistItem::factory()->create(['task_id' => $task->id]);

        Sanctum::actingAs($dev);

        $this->deleteJson("/api/checklist/{$item->id}")->assertNoContent();
        $this->assertDatabaseMissing('task_checklist_items', ['id' => $item->id]);
    }

    public function test_task_exposes_checklist_counts_for_the_board(): void
    {
        [$dev, $task] = $this->developerWithTask();
        TaskChecklistItem::factory()->count(2)->create(['task_id' => $task->id]);
        TaskChecklistItem::factory()->create(['task_id' => $task->id, 'is_done' => true]);

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks?project_id={$task->project_id}")
            ->assertOk()
            ->assertJsonPath('data.0.checklist_total', 3)
            ->assertJsonPath('data.0.checklist_done', 1);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.checklist_total', 3)
            ->assertJsonPath('data.checklist_done', 1);
    }

    public function test_checklist_is_limited_to_50_items_per_task(): void
    {
        [$dev, $task] = $this->developerWithTask();
        TaskChecklistItem::factory()->count(50)->create(['task_id' => $task->id]);

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/checklist", ['title' => 'Item ke-51'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseCount('task_checklist_items', 50);
    }

    public function test_developer_gets_404_for_other_team_checklist(): void
    {
        [$dev, $own] = $this->developerWithTask();
        $other = Task::factory()->create();
        $otherItem = TaskChecklistItem::factory()->create(['task_id' => $other->id]);

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks/{$own->id}/checklist")->assertOk(); // jaga-jaga: route memang ada
        $this->getJson("/api/tasks/{$other->id}/checklist")->assertNotFound();
        // Otorisasi sebelum validasi: body kosong/invalid tetap 404, bukan 422.
        $this->postJson("/api/tasks/{$other->id}/checklist", [])->assertNotFound();
        $this->putJson("/api/checklist/{$otherItem->id}", ['is_done' => 'bukan-boolean'])->assertNotFound();
        $this->deleteJson("/api/checklist/{$otherItem->id}")->assertNotFound();

        $this->assertDatabaseHas('task_checklist_items', ['id' => $otherItem->id, 'is_done' => false]);
    }

    public function test_management_can_read_but_not_write_checklist(): void
    {
        $task = Task::factory()->create();
        $item = TaskChecklistItem::factory()->create(['task_id' => $task->id]);

        Sanctum::actingAs($this->userWithRole('management'));

        $this->getJson("/api/tasks/{$task->id}/checklist")->assertOk()->assertJsonCount(1, 'data');
        // Otorisasi sebelum validasi: body kosong tetap 403, bukan 422.
        $this->postJson("/api/tasks/{$task->id}/checklist", [])->assertForbidden();
        $this->putJson("/api/checklist/{$item->id}", ['is_done' => true])->assertForbidden();
        $this->deleteJson("/api/checklist/{$item->id}")->assertForbidden();

        $this->assertDatabaseHas('task_checklist_items', ['id' => $item->id, 'is_done' => false]);
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void
    {
        $task = Task::factory()->create();

        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson("/api/tasks/{$task->id}/checklist")->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }

    #[DataProvider('invalidTitles')]
    public function test_item_title_validation(string $title): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/checklist", ['title' => $title])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public static function invalidTitles(): array
    {
        return [
            'title kosong' => [''],
            'title lebih dari 255 karakter' => [str_repeat('a', 256)],
        ];
    }

    public function test_is_done_must_be_boolean(): void
    {
        [$dev, $task] = $this->developerWithTask();
        $item = TaskChecklistItem::factory()->create(['task_id' => $task->id]);

        Sanctum::actingAs($dev);

        $this->putJson("/api/checklist/{$item->id}", ['is_done' => 'bukan-boolean'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_done');
    }

    public function test_deleting_a_task_deletes_its_checklist(): void
    {
        [$dev, $task] = $this->developerWithTask();
        TaskChecklistItem::factory()->count(2)->create(['task_id' => $task->id]);

        Sanctum::actingAs($dev);

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertDatabaseCount('task_checklist_items', 0);
    }
}
