<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TaskActivityNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function developer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('developer');

        return $user;
    }

    /** Dua developer di team yang sama, dan satu proyek. Mengembalikan [$ana, $budi, $project]. */
    private function teamOfTwo(): array
    {
        $ana = $this->developer();
        $budi = $this->developer();
        $team = Team::factory()->create();
        $team->members()->attach([$ana->id => ['role_in_team' => 'member'], $budi->id => ['role_in_team' => 'member']]);

        return [$ana, $budi, Project::factory()->create(['team_id' => $team->id])];
    }

    private function payload(Project $project, User $assignee): array
    {
        return [
            'project_id' => $project->id,
            'title' => 'Buat endpoint login',
            'assignee_id' => $assignee->id,
            'due_date' => '2026-11-20',
        ];
    }

    private function notify(User $user, string $message = 'Pesan'): void
    {
        $task = Task::factory()->create();
        $user->notify(new TaskActivityNotification($task, User::factory()->create(), 'assigned', $message));
    }

    // ---- Pemicu notifikasi ----

    public function test_creating_a_task_notifies_the_assignee(): void
    {
        [$ana, $budi, $project] = $this->teamOfTwo();

        Sanctum::actingAs($ana);

        $this->postJson('/api/tasks', $this->payload($project, $budi))->assertCreated();

        $this->assertCount(1, $budi->fresh()->notifications);
        $data = $budi->fresh()->notifications->first()->data;
        $this->assertSame('assigned', $data['kind']);
        $this->assertSame('Buat endpoint login', $data['task_title']);
        $this->assertSame($project->id, $data['project_id']);
        $this->assertStringContainsString($ana->name, $data['message']);
        $this->assertCount(0, $ana->fresh()->notifications);
    }

    public function test_assigning_a_task_to_yourself_does_not_notify(): void
    {
        [$ana, , $project] = $this->teamOfTwo();

        Sanctum::actingAs($ana);

        $this->postJson('/api/tasks', $this->payload($project, $ana))->assertCreated();

        $this->assertCount(0, $ana->fresh()->notifications);
    }

    public function test_reassigning_a_task_notifies_only_the_new_assignee(): void
    {
        [$ana, $budi, $project] = $this->teamOfTwo();
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $ana->id]);

        Sanctum::actingAs($ana);

        $this->putJson("/api/tasks/{$task->id}", ['assignee_id' => $budi->id])->assertOk();

        $this->assertSame('assigned', $budi->fresh()->notifications->first()->data['kind']);
        $this->assertCount(1, $budi->fresh()->notifications);
        $this->assertCount(0, $ana->fresh()->notifications);
    }

    public function test_due_date_change_notifies_the_assignee_unless_they_changed_it_themselves(): void
    {
        [$ana, $budi, $project] = $this->teamOfTwo();
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $budi->id, 'due_date' => '2026-11-20']);

        // Budi mengubah tenggat tasknya sendiri -> tidak ada notifikasi.
        Sanctum::actingAs($budi);
        $this->putJson("/api/tasks/{$task->id}", ['due_date' => '2026-11-25'])->assertOk();
        $this->assertCount(0, $budi->fresh()->notifications);

        // Ana mengubah tenggat task milik Budi -> Budi diberi tahu.
        Sanctum::actingAs($ana);
        $this->putJson("/api/tasks/{$task->id}", ['due_date' => '2026-12-01'])->assertOk();

        $this->assertCount(1, $budi->fresh()->notifications);
        $data = $budi->fresh()->notifications->first()->data;
        $this->assertSame('due_date_changed', $data['kind']);
        $this->assertStringContainsString('01/12/2026', $data['message']);
    }

    public function test_commenting_notifies_the_assignee_but_not_the_author(): void
    {
        [$ana, $budi, $project] = $this->teamOfTwo();
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $budi->id]);

        Sanctum::actingAs($ana);
        $this->postJson("/api/tasks/{$task->id}/updates", ['note' => 'Tolong cek PR-nya'])->assertCreated();

        $this->assertCount(1, $budi->fresh()->notifications);
        $this->assertSame('commented', $budi->fresh()->notifications->first()->data['kind']);

        // Budi mengomentari tasknya sendiri -> tidak ada notifikasi baru untuk siapa pun.
        Sanctum::actingAs($budi);
        $this->postJson("/api/tasks/{$task->id}/updates", ['note' => 'Siap'])->assertCreated();

        $this->assertCount(1, $budi->fresh()->notifications);
        $this->assertCount(0, $ana->fresh()->notifications);
    }

    // ---- Membaca dan menandai dibaca ----

    public function test_user_lists_only_own_notifications_newest_first_with_unread_count(): void
    {
        $me = $this->developer();
        $other = $this->developer();
        $this->notify($me, 'lama');
        $this->notify($me, 'baru');
        $this->notify($other, 'milik orang lain');
        $me->notifications()->where('data', 'like', '%lama%')->update(['created_at' => now()->subHour()]);

        Sanctum::actingAs($me);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta', 'unread_count'])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.message', 'baru')
            ->assertJsonPath('data.1.message', 'lama')
            ->assertJsonPath('unread_count', 2);
    }

    public function test_user_can_mark_own_notification_as_read_but_not_someone_elses(): void
    {
        $me = $this->developer();
        $other = $this->developer();
        $this->notify($me);
        $this->notify($other);
        $mine = $me->notifications()->first();
        $theirs = $other->notifications()->first();

        Sanctum::actingAs($me);

        $this->postJson("/api/notifications/{$mine->id}/read")->assertOk();
        $this->assertNotNull($mine->fresh()->read_at);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 0);

        $this->postJson("/api/notifications/{$theirs->id}/read")->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $me = $this->developer();
        $other = $this->developer();
        $this->notify($me);
        $this->notify($me);
        $this->notify($other);

        Sanctum::actingAs($me);

        $this->postJson('/api/notifications/read-all')->assertOk();

        $this->assertSame(0, $me->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }
}
