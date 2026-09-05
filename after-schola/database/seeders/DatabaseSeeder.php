<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Akun Management (admin tertinggi).
        $admin = User::firstOrCreate(
            ['email' => 'admin@afterschola.id'],
            ['name' => 'Admin Schola', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $admin->syncRoles('management');

        // Akun Trainer contoh.
        $trainer = User::firstOrCreate(
            ['email' => 'trainer@afterschola.id'],
            ['name' => 'Trainer Satu', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $trainer->syncRoles('trainer');

        // Data master contoh + penugasan trainer, agar API bisa langsung dicoba.
        $school = School::firstOrCreate(
            ['name' => 'SMP Contoh 1'],
            ['address' => 'Jl. Melati No. 1', 'pic_name' => 'Bu Sari', 'pic_phone' => '08123456789'],
        );
        $trainer->assignedSchools()->syncWithoutDetaching([$school->id]);

        foreach (['Beginner', 'Intermediate'] as $level) {
            Classroom::firstOrCreate(['school_id' => $school->id, 'name' => $level], ['level' => $level]);
        }
    }
}
