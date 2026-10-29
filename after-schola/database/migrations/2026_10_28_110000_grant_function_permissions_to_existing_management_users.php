<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permission fungsi yang sebelum Modul 0b diperoleh user management lewat role.
     * Daftar dibekukan di sini (bukan FunctionPermissions::ALL) agar migrasi tidak berubah di kemudian hari.
     */
    private array $held = ['manage users', 'verify registrations', 'manage invoices', 'confirm payments', 'view finance reports', 'manage branches', 'manage programs'];

    public function up(): void
    {
        $role = Role::where('name', 'management')->where('guard_name', 'web')->first();
        if (! $role) {
            return; // database baru: belum ada role/user
        }

        // Role management tidak lagi memegang permission ini (RoleSeeder). Pindahkan ke user yang ada
        // supaya tidak ada yang kehilangan akses (terutama manage users) saat deploy.
        foreach ($this->held as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role->users->each(fn ($user) => $user->givePermissionTo($this->held));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Tidak ada yang dibalik: permission langsung tidak berbahaya bila dibiarkan.
    }
};
