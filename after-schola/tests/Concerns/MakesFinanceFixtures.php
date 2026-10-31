<?php

namespace Tests\Concerns;

use App\Models\EnrollmentDecision;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\Registration;
use App\Models\TrialEvaluation;
use App\Models\User;
use App\Support\FunctionPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture bersama untuk test Modul 5 (Verifikasi & Pembayaran).
 */
trait MakesFinanceFixtures
{
    protected function seedRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    protected function makeUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.'_'.uniqid().'@test.id',
            'password' => Hash::make('password'),
            'is_active' => true,
            'branch_scope' => 'all', // fixture Modul 5 tidak membahas cabang; isolasi cabang diuji di test Modul 3-4
        ]);
        $user->assignRole($role);
        // Role management tidak lagi memegang permission fungsi; berikan langsung (Modul 0b).
        if ($role === 'management') {
            $user->givePermissionTo(FunctionPermissions::ALL);
        }

        return $user;
    }

    protected function makeProgram(float $price = 500000): Program
    {
        return Program::create([
            'name' => 'Coding Kids',
            'jenjang' => 'SD',
            'tipe' => 'offline',
            'price' => $price,
            'is_active' => true,
        ]);
    }

    /**
     * Rantai evaluasi trial -> keputusan "lanjut" -> pendaftaran (default: menunggu_verifikasi).
     */
    protected function makeRegistration(array $overrides = [], ?Program $program = null): Registration
    {
        $trainer = $this->makeUser('trainer');
        $evaluation = TrialEvaluation::create([
            'program_id' => $program?->id,
            'participant_name' => 'Budi Santoso',
            'trainer_id' => $trainer->id,
            'trial_date' => '2026-10-01',
            'attendance' => 'hadir',
        ]);
        $decision = EnrollmentDecision::create([
            'evaluation_id' => $evaluation->id,
            'decision' => 'lanjut',
            'decided_at' => '2026-10-02',
        ]);

        return Registration::create(array_merge([
            'decision_id' => $decision->id,
            'program_id' => $program?->id,
            'student_name' => 'Budi Santoso',
            'level' => 'beginner',
            'parent_name' => 'Ibu Budi',
            'parent_phone' => '081234567890',
            'status' => 'menunggu_verifikasi',
        ], $overrides));
    }

    protected function makeInvoice(?Registration $registration = null, array $overrides = []): Invoice
    {
        $registration ??= $this->makeRegistration(['status' => 'terverifikasi'], $this->makeProgram());
        static $seq = 0;
        $seq++;

        return Invoice::create(array_merge([
            'registration_id' => $registration->id,
            'number' => sprintf('INV/%s/T%04d', now()->format('Ym'), $seq),
            'amount' => 500000,
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'unpaid',
            'issued_at' => now(),
        ], $overrides));
    }
}
