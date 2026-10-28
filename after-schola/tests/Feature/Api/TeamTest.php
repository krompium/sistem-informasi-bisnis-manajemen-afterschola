<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamTest extends TestCase
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

    private function teamWith(User ...$members): Team
    {
        $team = Team::factory()->create();
        foreach ($members as $member) {
            $team->members()->attach($member->id, ['role_in_team' => 'member']);
        }

        return $team;
    }

    // ---- Developer: baca (hanya team sendiri) ----

    public function test_developer_index_lists_only_own_team(): void // T1
    {
        $dev = $this->userWithRole('developer');
        $own = $this->teamWith($dev);
        $this->teamWith($this->userWithRole('developer'));

        Sanctum::actingAs($dev);

        $this->getJson('/api/teams')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_developer_can_show_own_team_with_members(): void
    {
        $dev = $this->userWithRole('developer');
        $team = Team::factory()->create();
        $team->members()->attach($dev->id, ['role_in_team' => 'lead']);

        Sanctum::actingAs($dev);

        $this->getJson("/api/teams/{$team->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $team->id)
            ->assertJsonPath('data.members.0.id', $dev->id)
            ->assertJsonPath('data.members.0.role_in_team', 'lead');
    }

    public function test_developer_gets_404_for_other_team(): void // T2
    {
        $dev = $this->userWithRole('developer');
        $own = $this->teamWith($dev);
        $other = $this->teamWith($this->userWithRole('developer'));

        Sanctum::actingAs($dev);

        $this->getJson("/api/teams/{$own->id}")->assertOk(); // jaga-jaga: route memang ada
        $this->getJson("/api/teams/{$other->id}")->assertNotFound();
    }

    // ---- Management: lihat semua + kelola team ----

    public function test_management_sees_all_teams(): void // T4
    {
        $a = $this->teamWith($this->userWithRole('developer'));
        $b = $this->teamWith($this->userWithRole('developer'));

        Sanctum::actingAs($this->userWithRole('management'));

        $this->getJson('/api/teams')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/teams/{$a->id}")->assertOk();
        $this->getJson("/api/teams/{$b->id}")->assertOk();
    }

    public function test_management_can_create_team_with_members(): void // Q1: Management membuat team
    {
        $dev = $this->userWithRole('developer');
        Sanctum::actingAs($this->userWithRole('management'));

        $response = $this->postJson('/api/teams', [
            'name' => 'Tim Backend',
            'description' => 'Mengerjakan API',
            'members' => [['user_id' => $dev->id, 'role_in_team' => 'lead']],
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Tim Backend');
        $this->assertDatabaseHas('team_user', [
            'team_id' => $response->json('data.id'),
            'user_id' => $dev->id,
            'role_in_team' => 'lead',
        ]);
    }

    public function test_management_can_update_team_and_sync_members(): void
    {
        $old = $this->userWithRole('developer');
        $new = $this->userWithRole('developer');
        $team = $this->teamWith($old);

        Sanctum::actingAs($this->userWithRole('management'));

        $this->putJson("/api/teams/{$team->id}", [
            'name' => 'Nama Baru',
            'members' => [['user_id' => $new->id, 'role_in_team' => 'member']],
        ])->assertOk()->assertJsonPath('data.name', 'Nama Baru');

        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $new->id]);
        $this->assertDatabaseMissing('team_user', ['team_id' => $team->id, 'user_id' => $old->id]);
    }

    public function test_management_can_delete_empty_team(): void
    {
        $team = $this->teamWith($this->userWithRole('developer')); // anggota tidak menghalangi

        Sanctum::actingAs($this->userWithRole('management'));

        $this->deleteJson("/api/teams/{$team->id}")->assertNoContent();
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_team_with_projects_cannot_be_deleted(): void // Q5
    {
        $team = Team::factory()->create();
        Project::factory()->create(['team_id' => $team->id]);

        Sanctum::actingAs($this->userWithRole('management'));

        $this->deleteJson("/api/teams/{$team->id}")->assertUnprocessable();
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    // ---- Developer: tidak boleh menulis team ----

    public function test_developer_cannot_write_teams(): void // T5 (versi Q1 baru)
    {
        $dev = $this->userWithRole('developer');
        $own = $this->teamWith($dev);
        $other = $this->teamWith($this->userWithRole('developer'));

        Sanctum::actingAs($dev);

        // Urutan otorisasi sebelum validasi: body kosong tetap 403/404, bukan 422.
        $this->postJson('/api/teams', [])->assertForbidden();
        $this->putJson("/api/teams/{$own->id}", ['name' => 'Ganti'])->assertForbidden();
        $this->deleteJson("/api/teams/{$own->id}")->assertForbidden();

        // Team lain: 404, bukan 403 (konvensi §4.3).
        $this->putJson("/api/teams/{$other->id}", ['name' => ''])->assertNotFound();
        $this->deleteJson("/api/teams/{$other->id}")->assertNotFound();

        $this->assertDatabaseHas('teams', ['id' => $own->id, 'name' => $own->name]);
        $this->assertDatabaseHas('teams', ['id' => $other->id]);
    }

    // ---- Role lain ----

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_forbidden(string $role): void // T6
    {
        Sanctum::actingAs($this->userWithRole($role));

        $this->getJson('/api/teams')->assertForbidden();
        $this->postJson('/api/teams', ['name' => 'X'])->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [['trainer'], ['finance'], ['hr']];
    }

    // ---- Validasi (satu representatif per field) ----

    #[DataProvider('invalidTeamPayloads')]
    public function test_create_team_validation(array $override, string $errorKey): void
    {
        Sanctum::actingAs($this->userWithRole('management'));

        $this->postJson('/api/teams', array_merge(['name' => 'Tim X'], $override))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);
    }

    public static function invalidTeamPayloads(): array
    {
        return [
            'name wajib' => [['name' => ''], 'name'],
            'role_in_team di luar enum' => [
                ['members' => [['user_id' => 1, 'role_in_team' => 'boss']]],
                'members.0.role_in_team',
            ],
        ];
    }
}
