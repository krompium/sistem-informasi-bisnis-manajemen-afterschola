<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private $bdg;

    private $jkt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Staf Baru',
            'email' => 'baru@test.id',
            'password' => 'rahasia123',
            'role' => 'management',
            'branch_scope' => 'assigned',
            'branch_ids' => [$this->bdg->id],
            'permissions' => ['verify registrations'],
        ], $overrides);
    }

    // ---------- akses & listing ----------

    public function test_guest_401_and_user_without_manage_users_403(): void
    {
        $this->getJson('/api/users')->assertStatus(401);

        foreach (['trainer', 'finance', 'parent'] as $role) {
            Sanctum::actingAs($this->makeStaff($role, 'all'));
            $this->getJson('/api/users')->assertStatus(403);
        }
    }

    public function test_management_without_manage_users_can_still_list_users_within_scope(): void
    {
        // Dropdown trainer/developer di halaman lain dulu terbuka bagi semua Management (role),
        // sebelum manage users menjadi permission per user. Tetap dibatasi scope cabang.
        $inBdg = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $inJkt = $this->makeStaff('trainer', 'assigned', [$this->jkt->id]);
        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$this->bdg->id]));

        $ids = collect($this->getJson('/api/users?role=trainer')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertContains($inBdg->id, $ids);
        $this->assertNotContains($inJkt->id, $ids);
    }

    public function test_all_scope_lists_internal_users_but_never_parents(): void
    {
        $admin = $this->makeAdmin('all');
        $trainer = $this->makeStaff('trainer', 'assigned', [$this->jkt->id]);
        $parent = $this->makeStaff('parent');
        Sanctum::actingAs($admin);

        $ids = collect($this->getJson('/api/users')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($admin->id));
        $this->assertTrue($ids->contains($trainer->id));
        $this->assertFalse($ids->contains($parent->id));
    }

    public function test_assigned_scope_lists_only_assigned_users_sharing_a_branch(): void
    {
        $admin = $this->makeAdmin('assigned', [$this->bdg->id]);
        $inBdg = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $inBoth = $this->makeStaff('trainer', 'assigned', [$this->bdg->id, $this->jkt->id]);
        $inJkt = $this->makeStaff('trainer', 'assigned', [$this->jkt->id]);
        $pusat = $this->makeStaff('management', 'all', [$this->bdg->id]); // pusat tidak boleh dikelola staf cabang
        Sanctum::actingAs($admin);

        $ids = collect($this->getJson('/api/users')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$admin->id, $inBdg->id, $inBoth->id])->sort()->values()->all(), $ids);
        $this->assertNotContains($inJkt->id, $ids);
        $this->assertNotContains($pusat->id, $ids);
    }

    public function test_role_filter_still_works(): void
    {
        $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        Sanctum::actingAs($this->makeAdmin('all'));

        $roles = collect($this->getJson('/api/users?role=trainer')->assertOk()->json('data'))->pluck('roles.0')->unique()->all();

        $this->assertSame(['trainer'], $roles);
    }

    public function test_show_out_of_scope_user_or_parent_returns_404(): void
    {
        $inJkt = $this->makeStaff('trainer', 'assigned', [$this->jkt->id]);
        $pusat = $this->makeStaff('management', 'all');
        $parent = $this->makeStaff('parent');

        Sanctum::actingAs($this->makeAdmin('assigned', [$this->bdg->id]));
        $this->getJson("/api/users/{$inJkt->id}")->assertStatus(404);
        $this->getJson("/api/users/{$pusat->id}")->assertStatus(404);

        Sanctum::actingAs($this->makeAdmin('all'));
        $this->getJson("/api/users/{$parent->id}")->assertStatus(404);
    }

    // ---------- tambah user ----------

    public function test_store_creates_user_with_branches_and_function_permissions(): void
    {
        Sanctum::actingAs($this->makeAdmin('all'));

        $this->postJson('/api/users', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.branch_scope', 'assigned')
            ->assertJsonPath('data.branches.0.code', 'BDG')
            ->assertJsonPath('data.direct_permissions.0', 'verify registrations')
            ->assertJsonPath('data.roles.0', 'management');

        $user = User::where('email', 'baru@test.id')->firstOrFail();
        $this->assertTrue($user->hasDirectPermission('verify registrations'));
        $this->assertFalse($user->can('manage users'));
        $this->assertDatabaseHas('branch_user', ['user_id' => $user->id, 'branch_id' => $this->bdg->id]);
    }

    public function test_store_all_scope_user_needs_no_branches(): void
    {
        Sanctum::actingAs($this->makeAdmin('all'));

        $this->postJson('/api/users', $this->payload(['branch_scope' => 'all', 'branch_ids' => []]))
            ->assertStatus(201)
            ->assertJsonPath('data.branch_scope', 'all');
    }

    public function test_store_validation(): void
    {
        Sanctum::actingAs($this->makeAdmin('all'));

        // scope assigned wajib punya minimal satu cabang
        $this->postJson('/api/users', $this->payload(['branch_ids' => []]))->assertStatus(422)->assertJsonValidationErrors('branch_ids');
        // role parent bukan user internal
        $this->postJson('/api/users', $this->payload(['role' => 'parent']))->assertStatus(422)->assertJsonValidationErrors('role');
        // permission harus permission fungsi yang dikenal
        $this->postJson('/api/users', $this->payload(['permissions' => ['manage schools']]))->assertStatus(422)->assertJsonValidationErrors('permissions.0');
        $this->postJson('/api/users', $this->payload(['permissions' => ['bukan permission']]))->assertStatus(422)->assertJsonValidationErrors('permissions.0');
        $this->postJson('/api/users', $this->payload(['branch_scope' => 'semua']))->assertStatus(422)->assertJsonValidationErrors('branch_scope');
        $this->postJson('/api/users', $this->payload(['branch_ids' => [999]]))->assertStatus(422)->assertJsonValidationErrors('branch_ids.0');
        $this->postJson('/api/users', $this->payload(['email' => 'bukan-email']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame(1, User::count()); // hanya admin
    }

    public function test_assigned_scope_actor_cannot_escalate(): void
    {
        Sanctum::actingAs($this->makeAdmin('assigned', [$this->bdg->id]));

        $this->postJson('/api/users', $this->payload(['branch_scope' => 'all', 'branch_ids' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('branch_scope');
        $this->postJson('/api/users', $this->payload(['branch_ids' => [$this->jkt->id]]))
            ->assertStatus(422)->assertJsonValidationErrors('branch_ids.0');
        $this->postJson('/api/users', $this->payload(['branch_ids' => [$this->bdg->id]]))->assertStatus(201);
    }

    public function test_trainer_school_assignment_still_works(): void
    {
        $school = School::create(['name' => 'SMP Contoh']);
        Sanctum::actingAs($this->makeAdmin('all'));

        $this->postJson('/api/users', $this->payload([
            'role' => 'trainer', 'permissions' => [], 'school_ids' => [$school->id], 'subjects_taught' => ['Coding'], 'levels_taught' => ['beginner'],
        ]))->assertStatus(201);

        $trainer = User::where('email', 'baru@test.id')->firstOrFail();
        $this->assertSame([$school->id], $trainer->assignedSchoolIds());
        $this->assertSame(['Coding'], $trainer->subjects_taught);
    }

    // ---------- ubah user ----------

    public function test_update_syncs_role_permissions_and_branches(): void
    {
        $target = $this->makeStaff('trainer', 'assigned', [$this->bdg->id], ['manage leads']);
        Sanctum::actingAs($this->makeAdmin('all'));

        $this->putJson("/api/users/{$target->id}", [
            'role' => 'management', 'permissions' => ['manage classes'], 'branch_ids' => [$this->jkt->id],
        ])->assertOk()
            ->assertJsonPath('data.roles.0', 'management')
            ->assertJsonPath('data.direct_permissions.0', 'manage classes')
            ->assertJsonPath('data.branches.0.code', 'JKT');

        $target = $target->fresh();
        $this->assertFalse($target->hasDirectPermission('manage leads'));
        $this->assertSame([$this->jkt->id], $target->branches()->pluck('branches.id')->all());
    }

    public function test_update_without_password_keeps_password_and_allows_own_email(): void
    {
        $target = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $hash = $target->password;
        Sanctum::actingAs($this->makeAdmin('all'));

        $this->putJson("/api/users/{$target->id}", ['name' => 'Nama Baru', 'email' => $target->email])->assertOk();

        $this->assertSame('Nama Baru', $target->fresh()->name);
        $this->assertSame($hash, $target->fresh()->password);
    }

    public function test_assigned_scope_update_keeps_branches_outside_actor_scope(): void
    {
        $target = $this->makeStaff('trainer', 'assigned', [$this->bdg->id, $this->jkt->id]);
        Sanctum::actingAs($this->makeAdmin('assigned', [$this->bdg->id]));

        // Staf Bandung hanya "melihat" Bandung; mengubah daftar cabang tidak boleh menghapus Jakarta.
        $this->putJson("/api/users/{$target->id}", ['branch_ids' => [$this->bdg->id]])->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->bdg->id, $this->jkt->id],
            $target->fresh()->branches()->pluck('branches.id')->all(),
        );
    }

    public function test_update_out_of_scope_user_returns_404_even_with_empty_payload(): void
    {
        $inJkt = $this->makeStaff('trainer', 'assigned', [$this->jkt->id]);
        Sanctum::actingAs($this->makeAdmin('assigned', [$this->bdg->id]));

        $this->putJson("/api/users/{$inJkt->id}", [])->assertStatus(404);
        $this->putJson("/api/users/{$inJkt->id}", ['name' => 'Hack'])->assertStatus(404);
        $this->assertNotSame('Hack', $inJkt->fresh()->name);
    }

    // ---------- nonaktifkan (MGT-04) ----------

    public function test_deactivating_user_revokes_tokens_and_blocks_login(): void
    {
        $target = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $target->createToken('web');
        $this->assertSame(1, $target->tokens()->count());
        Sanctum::actingAs($this->makeAdmin('all'));

        $this->putJson("/api/users/{$target->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $target->tokens()->count());
        $this->postJson('/api/login', ['email' => $target->email, 'password' => 'password'])->assertStatus(422);
    }

    public function test_cannot_deactivate_own_account(): void
    {
        $admin = $this->makeAdmin('all');
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$admin->id}", ['is_active' => false])
            ->assertStatus(422)->assertJsonValidationErrors('is_active');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_users_are_never_deleted(): void
    {
        $target = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        Sanctum::actingAs($this->makeAdmin('all'));

        $this->deleteJson("/api/users/{$target->id}")->assertStatus(405);
        $this->assertTrue(User::whereKey($target->id)->exists());
    }
}
