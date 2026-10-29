<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FunctionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

class FunctionPermissionTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_seeder_creates_every_function_permission_from_prd(): void
    {
        $expected = [
            'manage programs', 'manage trial slots', 'manage leads', 'verify registrations',
            'manage classes', 'manage schedule', 'assign trainers', 'review reports',
            'view dashboard', 'view all attendance', 'view finance reports',
            'manage users', 'manage branches', 'manage integrations',
            'confirm payments', 'manage invoices', 'export trainer attendance',
        ];

        $this->assertEqualsCanonicalizing($expected, FunctionPermissions::ALL);
        foreach ($expected as $name) {
            $this->assertTrue(Permission::where('name', $name)->exists(), "permission '{$name}' belum di-seed");
        }
    }

    public function test_management_role_holds_no_function_permission(): void
    {
        $held = Role::findByName('management')->permissions->pluck('name')->all();

        $this->assertSame([], array_values(array_intersect($held, FunctionPermissions::ALL)));
    }

    public function test_finance_role_holds_finance_function_permissions(): void
    {
        $held = Role::findByName('finance')->permissions->pluck('name')->all();

        foreach (['confirm payments', 'manage invoices', 'view finance reports', 'export trainer attendance'] as $name) {
            $this->assertContains($name, $held);
        }
    }

    public function test_management_user_without_function_permission_is_forbidden(): void
    {
        $target = $this->makeStaff('trainer', 'all');
        Sanctum::actingAs($this->makeStaff('management', 'all'));

        // Kelola user (tulis/baca detail) butuh manage users; daftar user terbuka untuk dropdown (lihat UserManagementTest).
        $this->postJson('/api/users', ['name' => 'X'])->assertStatus(403);
        $this->getJson("/api/users/{$target->id}")->assertStatus(403);
        $this->putJson("/api/users/{$target->id}", ['name' => 'X'])->assertStatus(403);
        $this->getJson('/api/branches')->assertStatus(403);
        $this->getJson('/api/finance/aging')->assertStatus(403);
    }

    public function test_direct_function_permission_grants_exactly_that_function(): void
    {
        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['manage users']));

        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/branches')->assertOk(); // dropdown cabang untuk form user
        $this->postJson('/api/branches', ['name' => 'Baru', 'code' => 'NEW'])->assertStatus(403);
        $this->getJson('/api/finance/aging')->assertStatus(403);
    }

    public function test_existing_management_users_keep_access_after_migration(): void
    {
        // Kondisi sebelum Modul 0b: role management memegang permission fungsi lewat role.
        $legacy = ['manage users', 'verify registrations', 'manage invoices', 'confirm payments', 'view finance reports', 'manage branches', 'manage programs'];
        Role::findByName('management')->givePermissionTo($legacy);
        $user = User::create(['name' => 'Lama', 'email' => 'lama@test.id', 'password' => Hash::make('password'), 'is_active' => true]);
        $user->assignRole('management');
        $trainer = $this->makeStaff('trainer');

        (require database_path('migrations/2026_10_28_110000_grant_function_permissions_to_existing_management_users.php'))->up();

        $this->seedRoles(); // role management dibersihkan dari permission fungsi
        $user = $user->fresh();
        $this->assertEqualsCanonicalizing($legacy, $user->getDirectPermissions()->pluck('name')->all());
        $this->assertTrue($user->can('manage users'));
        $this->assertSame([], $trainer->fresh()->getDirectPermissions()->pluck('name')->all());
    }
}
