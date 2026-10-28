<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture bersama untuk test Modul 0 (cabang, scope cabang, user).
 */
trait MakesBranchFixtures
{
    protected function seedRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    protected function makeBranch(string $name = 'Cabang Bandung', string $code = 'BDG', array $overrides = []): Branch
    {
        return Branch::create(array_merge(['name' => $name, 'code' => $code, 'is_active' => true], $overrides));
    }

    /**
     * @param  array<int, int>  $branchIds  hanya bermakna untuk scope 'assigned'
     * @param  array<int, string>  $permissions  permission langsung (di luar permission role)
     */
    protected function makeStaff(string $role, string $scope = 'assigned', array $branchIds = [], array $permissions = []): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.'_'.uniqid().'@test.id',
            'password' => Hash::make('password'),
            'is_active' => true,
            'branch_scope' => $scope,
        ]);
        $user->assignRole($role);
        $user->branches()->sync($branchIds);
        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }
}
