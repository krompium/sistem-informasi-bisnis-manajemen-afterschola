<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache spatie agar perubahan langsung terpakai.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage schools',        // CRUD sekolah + penugasan trainer
            'manage classrooms',     // CRUD level per sekolah
            'manage students',       // CRUD murid penuh
            'add students',          // trainer menambah murid di sekolahnya
            'manage sessions',       // CRUD jadwal/pertemuan
            'input student attendance',
            'input trainer attendance',
            'view attendance',       // lihat rekap absensi
            'manage expo reports',   // lihat & rekap semua laporan ekspo
            'submit expo reports',   // trainer mengisi laporan ekspo
            'export data',           // export Excel/PDF
            'manage users',          // kelola user & role
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'management' => $permissions, // akses penuh
            'finance' => ['view attendance', 'export data'],
            'hr' => ['view attendance', 'export data'],
            'trainer' => [
                'add students',
                'input student attendance',
                'input trainer attendance',
                'submit expo reports',
                'export data',
            ],
            'developer' => [], // modul manajemen proyek (fase 3), belum ada di absensi
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
