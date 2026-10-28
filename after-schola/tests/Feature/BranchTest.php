<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/branches')->assertStatus(401);
    }

    public function test_user_without_manage_branches_permission_gets_403(): void
    {
        foreach (['trainer', 'finance', 'parent'] as $role) {
            Sanctum::actingAs($this->makeStaff($role, 'all'));
            $this->getJson('/api/branches')->assertStatus(403);
            $this->postJson('/api/branches', ['name' => 'X', 'code' => 'XXX'])->assertStatus(403);
        }
    }

    public function test_all_scope_lists_every_branch(): void
    {
        $this->makeBranch('Cabang Bandung', 'BDG');
        $this->makeBranch('Cabang Jakarta', 'JKT');
        Sanctum::actingAs($this->makeStaff('management', 'all'));

        $this->getJson('/api/branches')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_assigned_scope_lists_only_own_branches(): void
    {
        $bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->makeBranch('Cabang Jakarta', 'JKT');
        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$bdg->id]));

        $this->getJson('/api/branches')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BDG');
    }

    public function test_all_scope_can_create_branch(): void
    {
        Sanctum::actingAs($this->makeStaff('management', 'all'));

        $this->postJson('/api/branches', ['name' => 'Cabang Bandung', 'code' => 'BDG', 'address' => 'Jl. Braga 1'])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'BDG')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('branches', ['code' => 'BDG', 'name' => 'Cabang Bandung']);
    }

    public function test_assigned_scope_cannot_create_branch(): void
    {
        $bdg = $this->makeBranch();
        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$bdg->id]));

        $this->postJson('/api/branches', ['name' => 'Cabang Baru', 'code' => 'NEW'])->assertStatus(403);
        $this->assertDatabaseMissing('branches', ['code' => 'NEW']);
    }

    public function test_create_validates_name_and_unique_code_format(): void
    {
        $this->makeBranch('Cabang Bandung', 'BDG');
        Sanctum::actingAs($this->makeStaff('management', 'all'));

        $this->postJson('/api/branches', ['code' => 'JKT'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/branches', ['name' => 'Dobel', 'code' => 'BDG'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/branches', ['name' => 'Salah Format', 'code' => 'jk t!'])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_show_and_update_in_scope(): void
    {
        $bdg = $this->makeBranch();
        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$bdg->id]));

        $this->getJson("/api/branches/{$bdg->id}")->assertOk()->assertJsonPath('data.name', 'Cabang Bandung');
        $this->putJson("/api/branches/{$bdg->id}", ['name' => 'Cabang Bandung Pusat', 'code' => 'BDG'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cabang Bandung Pusat');
    }

    public function test_out_of_scope_branch_returns_404_not_403(): void
    {
        $bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$bdg->id]));

        $this->getJson("/api/branches/{$jkt->id}")->assertStatus(404);
        // Payload kosong tetap 404 (bukan 422) agar keberadaan cabang tidak bocor.
        $this->putJson("/api/branches/{$jkt->id}", [])->assertStatus(404);
        $this->putJson("/api/branches/{$jkt->id}", ['name' => 'Hack', 'code' => 'JKT'])->assertStatus(404);
        $this->assertDatabaseHas('branches', ['id' => $jkt->id, 'name' => 'Cabang Jakarta']);
    }

    public function test_branch_can_be_deactivated_and_is_never_deleted(): void
    {
        $bdg = $this->makeBranch();
        Sanctum::actingAs($this->makeStaff('management', 'all'));

        $this->putJson("/api/branches/{$bdg->id}", ['name' => $bdg->name, 'code' => $bdg->code, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // MGT-04: entitas tidak dihapus permanen — tidak ada endpoint DELETE.
        $this->deleteJson("/api/branches/{$bdg->id}")->assertStatus(405);
        $this->assertSame(1, Branch::count());
    }
}
