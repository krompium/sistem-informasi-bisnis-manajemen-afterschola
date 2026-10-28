<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectExportTest extends TestCase
{
    use RefreshDatabase;

    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

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

    /** Developer anggota team + proyek di team itu. */
    private function developerWithProject(array $projectAttributes = []): array
    {
        $dev = $this->userWithRole('developer');
        $team = Team::factory()->create();
        $team->members()->attach($dev->id, ['role_in_team' => 'member']);

        return [$dev, Project::factory()->create(array_merge(['team_id' => $team->id], $projectAttributes))];
    }

    /** Semua nilai sel dari file xlsx hasil export, diratakan jadi satu daftar. */
    private function xlsxCells(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $cells = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                array_push($cells, ...$row->toArray());
            }
        }
        $reader->close();
        unlink($path);

        return $cells;
    }

    public function test_developer_exports_only_the_tasks_of_the_project_to_excel(): void
    {
        [$dev, $project] = $this->developerWithProject(['name' => 'API Absensi']);
        $sibling = Project::factory()->create(['team_id' => $project->team_id]);
        Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id, 'title' => 'Task Satu']);
        Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id, 'title' => 'Task Dua']);
        Task::factory()->create(['project_id' => $sibling->id, 'assignee_id' => $dev->id, 'title' => 'Task Proyek Lain']);
        Task::factory()->create(['title' => 'Task Team Lain']);

        Sanctum::actingAs($dev);

        $response = $this->get("/api/projects/{$project->id}/export?format=xlsx")
            ->assertOk()
            ->assertHeader('content-type', self::XLSX);
        $cells = $this->xlsxCells($response);

        $this->assertContains('LAPORAN PROGRES PROYEK', $cells);
        $this->assertContains('API Absensi', $cells);
        $this->assertContains('Task Satu', $cells);
        $this->assertContains('Task Dua', $cells);
        $this->assertNotContains('Task Proyek Lain', $cells);
        $this->assertNotContains('Task Team Lain', $cells);
    }

    public function test_management_can_export_any_project(): void
    {
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id, 'title' => 'Task Management']);

        Sanctum::actingAs($this->userWithRole('management'));

        $cells = $this->xlsxCells($this->get("/api/projects/{$project->id}/export?format=xlsx")->assertOk());

        $this->assertContains('Task Management', $cells);
    }

    public function test_developer_exports_own_project_to_pdf(): void
    {
        [$dev, $project] = $this->developerWithProject();
        Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $dev->id]);

        Sanctum::actingAs($dev);

        $response = $this->get("/api/projects/{$project->id}/export?format=pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_developer_gets_404_for_other_team_project_export(): void
    {
        [$dev, $own] = $this->developerWithProject();
        $other = Project::factory()->create();

        Sanctum::actingAs($dev);

        $this->get("/api/projects/{$own->id}/export?format=xlsx")->assertOk(); // jaga-jaga: route memang ada
        $this->get("/api/projects/{$other->id}/export?format=xlsx")->assertNotFound();
        $this->get("/api/projects/{$other->id}/export?format=pdf")->assertNotFound();
        // Otorisasi sebelum validasi: format invalid dari non-anggota tetap 404, bukan 422.
        $this->get("/api/projects/{$other->id}/export?format=bogus")->assertNotFound();
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void
    {
        $project = Project::factory()->create();

        Sanctum::actingAs($this->userWithRole($role));

        $this->get("/api/projects/{$project->id}/export?format=xlsx")->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }

    #[DataProvider('invalidFormats')]
    public function test_export_requires_a_valid_format(string $query): void
    {
        [$dev, $project] = $this->developerWithProject();

        Sanctum::actingAs($dev);

        $this->getJson("/api/projects/{$project->id}/export{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('format');
    }

    public static function invalidFormats(): array
    {
        return [
            'format tidak dikirim' => [''],
            'format tidak dikenal' => ['?format=docx'],
        ];
    }
}
