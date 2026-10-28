<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
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

        return [$dev, Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id])];
    }

    // ---- Unggah ----

    public function test_developer_uploads_a_file_stored_privately_under_a_random_name(): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);

        $response = $this->post("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('spesifikasi-api.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'spesifikasi-api.pdf')
            ->assertJsonPath('data.mime', 'application/pdf')
            ->assertJsonPath('data.user.id', $dev->id)
            ->assertJsonMissingPath('data.path');

        $path = TaskAttachment::findOrFail($response->json('data.id'))->path;

        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith("task-attachments/{$task->id}/", $path);
        $this->assertStringNotContainsString('spesifikasi-api', $path); // nama klien tidak dipakai
        $this->assertStringEndsWith('.pdf', $path);
    }

    #[DataProvider('disallowedFiles')]
    public function test_disallowed_file_types_are_rejected(string $name, string $mime, ?string $content): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);

        $file = $content === null
            ? UploadedFile::fake()->create($name, 10, $mime)
            : UploadedFile::fake()->createWithContent($name, $content);

        $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('task_attachments', 0);
    }

    public static function disallowedFiles(): array
    {
        return [
            'skrip php' => ['shell.php', 'application/x-php', null],
            'svg (bisa memuat skrip)' => ['gambar.svg', 'image/svg+xml', null],
            'html' => ['halaman.html', 'text/html', null],
            'berekstensi pdf tetapi isinya php' => ['palsu.pdf', 'application/pdf', '<?php echo 1;'],
        ];
    }

    public function test_file_larger_than_10_mb_is_rejected(): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_file_is_required(): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/attachments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_a_task_is_limited_to_20_attachments(): void
    {
        [$dev, $task] = $this->developerWithTask();
        TaskAttachment::factory()->count(20)->create(['task_id' => $task->id, 'user_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('ke-21.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('task_attachments', 20);
    }

    // ---- Akses: daftar dan unduh ----

    public function test_developer_lists_attachments_with_uploader_and_gets_404_for_other_team(): void
    {
        [$dev, $own] = $this->developerWithTask();
        TaskAttachment::factory()->create(['task_id' => $own->id, 'user_id' => $dev->id, 'original_name' => 'catatan.pdf']);
        $other = Task::factory()->create();

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks/{$own->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_name', 'catatan.pdf')
            ->assertJsonPath('data.0.user.name', $dev->name);

        $this->getJson("/api/tasks/{$other->id}/attachments")->assertNotFound();
        // Otorisasi sebelum validasi: tanpa file pun tetap 404, bukan 422.
        $this->postJson("/api/tasks/{$other->id}/attachments", [])->assertNotFound();
    }

    public function test_download_returns_the_file_for_authorized_users_and_404_for_other_teams(): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);
        $id = $this->post("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->createWithContent('catatan.txt', 'isi dokumen'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $response = $this->get("/api/attachments/{$id}/download")->assertOk();
        $this->assertSame('isi dokumen', $response->streamedContent());
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('catatan.txt', $response->headers->get('content-disposition'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));

        // Management boleh mengunduh.
        Sanctum::actingAs($this->userWithRole('management'));
        $this->get("/api/attachments/{$id}/download")->assertOk();

        // Developer dari team lain: 404.
        Sanctum::actingAs($this->userWithRole('developer'));
        $this->get("/api/attachments/{$id}/download")->assertNotFound();
    }

    public function test_management_can_read_but_not_write_attachments(): void
    {
        $task = Task::factory()->create();
        $attachment = TaskAttachment::factory()->create(['task_id' => $task->id]);

        Sanctum::actingAs($this->userWithRole('management'));

        $this->getJson("/api/tasks/{$task->id}/attachments")->assertOk()->assertJsonCount(1, 'data');
        // Otorisasi sebelum validasi: tanpa file pun tetap 403, bukan 422.
        $this->postJson("/api/tasks/{$task->id}/attachments", [])->assertForbidden();
        $this->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();
        $this->assertDatabaseCount('task_attachments', 1);
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void
    {
        $task = Task::factory()->create();

        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson("/api/tasks/{$task->id}/attachments")->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }

    // ---- Hapus ----

    public function test_developer_deletes_own_attachment_and_its_file_but_gets_404_for_other_team(): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);
        $id = $this->post("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('hapus.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $path = TaskAttachment::findOrFail($id)->path;

        $otherTask = Task::factory()->create();
        $otherPath = 'task-attachments/'.$otherTask->id.'/orang-lain.pdf';
        Storage::disk('local')->put($otherPath, 'x');
        $other = TaskAttachment::factory()->create(['task_id' => $otherTask->id, 'path' => $otherPath]);

        $this->deleteJson("/api/attachments/{$id}")->assertNoContent();
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('task_attachments', ['id' => $id]);

        $this->deleteJson("/api/attachments/{$other->id}")->assertNotFound();
        Storage::disk('local')->assertExists($otherPath);
        $this->assertDatabaseHas('task_attachments', ['id' => $other->id]);
    }

    public function test_deleting_a_task_deletes_its_attachment_files(): void
    {
        [$dev, $task] = $this->developerWithTask();

        Sanctum::actingAs($dev);
        $id = $this->post("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('ikut-hilang.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $path = TaskAttachment::findOrFail($id)->path;
        Storage::disk('local')->assertExists($path);

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('task_attachments', 0);
    }

    public function test_task_exposes_attachment_count_for_the_board(): void
    {
        [$dev, $task] = $this->developerWithTask();
        TaskAttachment::factory()->count(2)->create(['task_id' => $task->id]);

        Sanctum::actingAs($dev);

        $this->getJson("/api/tasks?project_id={$task->project_id}")
            ->assertOk()
            ->assertJsonPath('data.0.attachments_count', 2);
    }
}
