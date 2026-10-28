<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

class ParentRoleTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_parent_role_exists_without_permissions(): void
    {
        $role = Role::findByName('parent');

        $this->assertCount(0, $role->permissions);
    }

    public function test_parent_can_login_and_me_exposes_branch_scope(): void
    {
        $parent = $this->makeStaff('parent');

        $this->postJson('/api/login', ['email' => $parent->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.roles.0', 'parent');

        Sanctum::actingAs($parent);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('data.roles.0', 'parent')
            ->assertJsonPath('data.branch_scope', 'assigned')
            ->assertJsonPath('data.branches', []);
    }

    public function test_me_lists_assigned_branches_of_staff(): void
    {
        $bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->makeBranch('Cabang Jakarta', 'JKT');
        Sanctum::actingAs($this->makeStaff('trainer', 'assigned', [$bdg->id]));

        $this->getJson('/api/me')->assertOk()
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonPath('data.branches.0.code', 'BDG');
    }

    public function test_new_users_default_to_assigned_scope(): void
    {
        $this->assertSame('assigned', $this->makeStaff('trainer')->fresh()->branch_scope);
    }
}
