<?php

namespace Database\Seeders;

use App\Support\FunctionPermissions;
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

        // Permission lama (absensi sekolah, trial, pendaftaran interim). Role management memegang semuanya.
        $legacyPermissions = [
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
            'submit trial evaluations', // trainer mengisi evaluasi trial
            'manage trial evaluations', // lihat & kelola semua evaluasi trial
            'manage enrollment',        // keputusan lanjut/tidak + pendaftaran resmi
            'manage hot issues',     // buat/ubah/nonaktifkan hot issue (popup notifikasi semua role)
        ];

        // Permission fungsi (PRD 3.2): diberikan per user, bukan lewat role management.
        foreach (array_merge($legacyPermissions, FunctionPermissions::ALL) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'management' => $legacyPermissions, // permission fungsi (FunctionPermissions) diberikan per user
            'finance' => ['view attendance', 'export data', 'manage invoices', 'confirm payments', 'view finance reports', 'export trainer attendance'],
            'hr' => ['view attendance', 'export data'],
            'trainer' => [
                'add students',
                'input student attendance',
                'input trainer attendance',
                'submit expo reports',
                'submit trial evaluations',
                'export data',
            ],
            'parent' => [], // Orangtua: akses lewat kepemilikan anak (students.parent_id), bukan permission
            'developer' => [], // modul manajemen proyek (fase 3), belum ada di absensi
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
