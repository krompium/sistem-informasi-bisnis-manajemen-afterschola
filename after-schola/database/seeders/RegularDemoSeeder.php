<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\EnrollmentDecision;
use App\Models\Evaluation;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\ProgressNote;
use App\Models\Program;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TrainerAssignment;
use App\Models\TrainerAttendance;
use App\Models\TrialEvaluation;
use App\Models\TrialSlot;
use App\Models\User;
use App\Support\FunctionPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo dan UAT jalur reguler: php artisan db:seed --class=RegularDemoSeeder
 * Hanya untuk lingkungan demo/lokal, jangan dijalankan di produksi. DemoSeeder lama tetap untuk data ekstrakurikuler (sekolah).
 * Semua akun berpassword "password". Anak ortu tersebar di setiap tahap alur supaya demo bisa dimulai dari tahap mana pun:
 * Andi (trial dijadwalkan) sampai Hana, Ilham, Joko (aktif; raport terkirim, ditinjau, draft) dan Kiki (leads).
 * Langkah demo per skenario ada di dokumen "Skenario Pengujian dan Demo".
 */
class RegularDemoSeeder extends Seeder
{
    private User $keuangan;

    private User $admin;

    public function run(): void
    {
        $this->call(RoleSeeder::class);
        if (User::where('email', 'admin@demo.id')->exists()) {
            $this->command?->warn('Data demo reguler sudah ada, dilewati.');

            return;
        }

        $bdg = Branch::create(['name' => 'Cabang Bandung', 'code' => 'BDG', 'address' => 'Jl. Dipatiukur No. 35, Bandung', 'is_active' => true]);
        $jkt = Branch::create(['name' => 'Cabang Jakarta', 'code' => 'JKT', 'address' => 'Jl. Margonda Raya No. 10, Depok', 'is_active' => true]);

        $coding = $this->program('Coding Kids', 'SD', 500000, ['Dasar', 'Menengah']);
        $robotik = $this->program('Robotik Junior', 'SMP', 650000, ['Dasar']);

        // ---- akun staf (password: password) ----
        $this->admin = $this->staff('admin@demo.id', 'Admin Pusat', 'management', 'all', [], FunctionPermissions::ALL);
        $this->staff('pendaftaran@demo.id', 'Admin Pendaftaran Bandung', 'management', 'assigned', [$bdg->id], ['manage trial slots', 'manage leads', 'verify registrations']);
        $koordinator = $this->staff('koordinator@demo.id', 'Koordinator Program Bandung', 'management', 'assigned', [$bdg->id], ['manage classes', 'assign trainers', 'manage schedule', 'review reports', 'view dashboard', 'view all attendance']);
        $this->staff('pimpinan@demo.id', 'Pimpinan', 'management', 'all', [], ['view dashboard', 'view all attendance', 'view finance reports']);
        $this->keuangan = $this->staff('keuangan@demo.id', 'Keuangan', 'finance', 'assigned', [$bdg->id, $jkt->id]);
        $rina = $this->staff('trainer1@demo.id', 'Rina (Trainer Utama)', 'trainer', 'assigned', [$bdg->id], [], 'KEU-001');
        $budi = $this->staff('trainer2@demo.id', 'Budi (Asisten)', 'trainer', 'assigned', [$bdg->id], [], 'KEU-002');
        $sari = $this->staff('trainer4@demo.id', 'Sari (Pengganti)', 'trainer', 'assigned', [$bdg->id], [], 'KEU-004');
        $dewi = $this->staff('trainer3@demo.id', 'Dewi (Trainer Jakarta)', 'trainer', 'assigned', [$jkt->id], [], 'KEU-003');

        // ---- slot trial: yang sudah lewat (untuk evaluasi) dan yang mendatang (untuk pendaftaran baru) ----
        $pastSlot = $this->slot($bdg, $coding, $rina, now()->subDays(2));
        $futureSlot = $this->slot($bdg, $coding, $rina, now()->addDays(3));
        $this->slot($jkt, $robotik, $dewi, now()->addDays(4));
        $jktPastSlot = $this->slot($jkt, $robotik, $dewi, now()->subDays(2));

        // ---- anak di setiap tahap alur ----
        $this->child('ortu1', 'Ibu Ani', 'Andi', $bdg, $coding, $futureSlot, 'scheduled');
        $this->child('ortu2', 'Ibu Bella', 'Bima', $bdg, $coding, $pastSlot, 'evaluated');
        $this->child('ortu3', 'Ibu Cinta', 'Citra', $bdg, $coding, $pastSlot, 'decided');
        $this->child('ortu4', 'Ibu Dina', 'Dian', $bdg, $coding, $pastSlot, 'waiting');
        $this->child('ortu5', 'Ibu Eka', 'Eka', $bdg, $coding, $pastSlot, 'revision');
        $this->child('ortu6', 'Ibu Fani', 'Fajar', $bdg, $coding, $pastSlot, 'unpaid');
        $this->child('ortu7', 'Ibu Gina', 'Gita', $bdg, $coding, $pastSlot, 'pending_payment');
        $this->child('ortu8', 'Ibu Hesti', 'Hari', $bdg, $coding, $pastSlot, 'paid');
        $this->child('ortu9', 'Ibu Indah', 'Kiki', $bdg, $coding, $pastSlot, 'declined');

        // ---- kelas Bandung dengan tiga siswa aktif ----
        $classA = $this->classGroup($bdg, $coding, 'Coding Kids A (Dasar)', 8);
        $this->classGroup($bdg, $coding, 'Coding Kids B (Dasar)', 8);
        TrainerAssignment::create(['class_group_id' => $classA->id, 'trainer_id' => $rina->id, 'role' => 'utama']);
        TrainerAssignment::create(['class_group_id' => $classA->id, 'trainer_id' => $budi->id, 'role' => 'asisten']);

        $active = [];
        foreach ([['ortu10', 'Ibu Hana', 'Hana'], ['ortu11', 'Ibu Ilma', 'Ilham'], ['ortu12', 'Ibu Joni', 'Joko']] as [$login, $parentName, $childName]) {
            [, $registration] = $this->child($login, $parentName, $childName, $bdg, $coding, $pastSlot, 'active');
            $active[$childName] = $this->enroll($registration, $classA, now()->subDays(25));
        }
        $this->sessions($classA, $active, $rina, $sari);
        $this->reports($active, $coding, $rina, $koordinator);

        // ---- Jakarta: satu kelas dan satu siswa aktif (data lintas cabang) ----
        $classJ = $this->classGroup($jkt, $robotik, 'Robotik Junior A', 6);
        TrainerAssignment::create(['class_group_id' => $classJ->id, 'trainer_id' => $dewi->id, 'role' => 'utama']);
        [, $linaRegistration] = $this->child('ortu13', 'Ibu Lilis', 'Lina', $jkt, $robotik, $jktPastSlot, 'active');
        $lina = $this->enroll($linaRegistration, $classJ, now()->subDays(10));
        $session = ClassSession::create(['class_group_id' => $classJ->id, 'trainer_id' => $dewi->id, 'mode' => 'onsite', 'date' => now()->subDays(3)->toDateString(), 'start_time' => '15:00', 'end_time' => '16:30', 'meeting_no' => 1, 'is_locked' => true]);
        StudentAttendance::create(['class_session_id' => $session->id, 'student_id' => $lina->student_id, 'is_present' => true]);
        TrainerAttendance::create(['class_session_id' => $session->id, 'trainer_id' => $dewi->id, 'status' => 'hadir', 'check_in_at' => now()->subDays(3)->setTime(8, 5), 'latitude' => -6.3689, 'longitude' => 106.8337]);

        $this->command?->info('Data demo reguler siap. Login: admin@demo.id / password (daftar akun di dokumen skenario).');
    }

    // ---------------------------------------------------------------- builders

    private function staff(string $email, string $name, string $role, string $scope, array $branchIds = [], array $permissions = [], ?string $externalRef = null): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make('password'), 'is_active' => true, 'branch_scope' => $scope, 'external_ref' => $externalRef]);
        $user->assignRole($role);
        $user->branches()->sync($branchIds);
        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    private function program(string $name, string $jenjang, int $price, array $levels): Program
    {
        $program = Program::create(['name' => $name, 'jenjang' => $jenjang, 'tipe' => 'offline', 'price' => $price, 'is_active' => true]);
        foreach ($levels as $order => $level) {
            $program->levels()->create(['name' => $level, 'sort_order' => $order + 1]);
        }
        foreach (['Logika' => 10, 'Kreativitas' => 10, 'Kolaborasi' => 10] as $criterion => $max) {
            $program->rubricCriteria()->create(['name' => $criterion, 'max_score' => $max]);
        }

        return $program;
    }

    private function slot(Branch $branch, Program $program, User $trainer, $startsAt): TrialSlot
    {
        return TrialSlot::create(['branch_id' => $branch->id, 'program_id' => $program->id, 'trainer_id' => $trainer->id, 'starts_at' => $startsAt, 'capacity' => 10]);
    }

    private function classGroup(Branch $branch, Program $program, string $name, int $capacity): ClassGroup
    {
        return ClassGroup::create(['program_id' => $program->id, 'level_id' => $program->levels()->first()->id, 'branch_id' => $branch->id, 'name' => $name, 'capacity' => $capacity, 'is_active' => true]);
    }

    /**
     * Anak (lead) pada tahap tertentu: scheduled, evaluated, decided, waiting, revision, unpaid, pending_payment, paid, active, declined.
     * Mengembalikan [lead, registrasi atau null].
     */
    private function child(string $login, string $parentName, string $childName, Branch $branch, Program $program, TrialSlot $slot, string $stage): array
    {
        $parent = User::create(['name' => $parentName, 'email' => $login.'@demo.id', 'phone' => '0812345678'.random_int(10, 99), 'password' => Hash::make('password'), 'is_active' => true, 'branch_scope' => 'assigned']);
        $parent->assignRole('parent');

        $lead = Lead::create([
            'name' => $childName, 'education_level' => $program->jenjang === 'SMP' ? 'smp' : 'sd', 'origin_school' => 'SD Negeri 1 '.$branch->name,
            'parent_email' => $parent->email, 'parent_id' => $parent->id, 'branch_id' => $branch->id, 'program_id' => $program->id,
            'trial_slot_id' => $slot->id, 'status' => 'trial_scheduled', 'consented_at' => now(),
        ]);
        if ($stage === 'scheduled') {
            return [$lead, null];
        }

        $evaluation = TrialEvaluation::create([
            'lead_id' => $lead->id, 'branch_id' => $branch->id, 'trial_slot_id' => $slot->id, 'program_id' => $program->id,
            'participant_name' => $childName, 'origin_school' => $lead->origin_school, 'trainer_id' => $slot->trainer_id,
            'trial_date' => today()->subDays(2), 'attendance' => 'hadir', 'score_enthusiasm' => 4, 'score_understanding' => 5,
            'score_focus' => 3, 'score_collaboration' => 4, 'strengths' => 'Cepat memahami logika dasar', 'improvements' => 'Fokus perlu dilatih',
            'recommended_level_id' => $program->levels()->first()->id, 'recommendation' => 'lanjut',
        ]);
        if ($stage === 'evaluated') {
            return [$lead, null];
        }

        $decision = EnrollmentDecision::create(['evaluation_id' => $evaluation->id, 'decision' => $stage === 'declined' ? 'tidak' : 'lanjut', 'decided_at' => today(), 'decided_by' => $parent->id]);
        if ($stage === 'declined') {
            $lead->update(['status' => 'not_continued']);

            return [$lead, null];
        }
        if ($stage === 'decided') {
            return [$lead, null];
        }

        $verified = in_array($stage, ['unpaid', 'pending_payment', 'paid', 'active'], true);
        $registration = Registration::create([
            'decision_id' => $decision->id, 'lead_id' => $lead->id, 'branch_id' => $branch->id, 'program_id' => $program->id, 'type' => 'baru',
            'student_name' => $childName, 'origin_school' => $lead->origin_school, 'level' => $program->levels()->first()->name,
            'parent_name' => $parentName, 'parent_phone' => $parent->phone, 'parent_email' => $parent->email,
            'status' => match (true) { $stage === 'waiting' => 'menunggu_verifikasi', $stage === 'revision' => 'perlu_revisi', default => 'terverifikasi' },
            'revision_note' => $stage === 'revision' ? 'Mohon lengkapi tanggal lahir dan nama sekolah asal.' : null,
            'verified_by' => $verified ? $this->admin->id : null, 'verified_at' => $verified ? now() : null,
        ]);
        if (! $verified) {
            return [$lead, $registration];
        }

        $paid = in_array($stage, ['paid', 'active'], true);
        $invoice = Invoice::create([
            'registration_id' => $registration->id, 'number' => Invoice::nextNumber($branch->code), 'amount' => $program->price,
            'due_date' => now()->addDays(7)->toDateString(), 'status' => $paid ? 'paid' : 'unpaid', 'issued_at' => now(), 'paid_at' => $paid ? now() : null,
        ]);
        if ($paid || $stage === 'pending_payment') {
            Payment::create([
                'invoice_id' => $invoice->id, 'amount' => $program->price, 'method' => 'transfer', 'paid_date' => today(),
                'status' => $paid ? 'confirmed' : 'pending', 'confirmed_by' => $paid ? $this->keuangan->id : null, 'confirmed_at' => $paid ? now() : null,
            ]);
        }

        return [$lead, $registration];
    }

    private function enroll(Registration $registration, ClassGroup $group, $start): Enrollment
    {
        $student = Student::create(['name' => $registration->student_name, 'branch_id' => $registration->branch_id, 'parent_id' => $registration->lead->parent_id]);
        $registration->update(['student_id' => $student->id]);

        return Enrollment::create(['class_group_id' => $group->id, 'student_id' => $student->id, 'start_date' => $start->toDateString(), 'status' => 'aktif']);
    }

    /** Tiga sesi lalu (minggu ke-3 diisi Sari sebagai pengganti) dan satu sesi hari ini tanpa check-in (untuk demo absensi). */
    private function sessions(ClassGroup $group, array $enrollments, User $rina, User $sari): void
    {
        $plan = [[21, $rina, 'hadir'], [14, $rina, 'terlambat'], [7, $sari, 'hadir']];
        foreach ($plan as $index => [$daysAgo, $trainer, $status]) {
            $session = ClassSession::create(['class_group_id' => $group->id, 'trainer_id' => $trainer->id, 'mode' => 'onsite', 'date' => now()->subDays($daysAgo)->toDateString(), 'start_time' => '15:00', 'end_time' => '16:30', 'meeting_no' => $index + 1, 'is_locked' => true]);
            TrainerAttendance::create(['class_session_id' => $session->id, 'trainer_id' => $trainer->id, 'status' => $status, 'check_in_at' => now()->subDays($daysAgo)->setTime($status === 'terlambat' ? 8 : 7, $status === 'terlambat' ? 25 : 50), 'latitude' => -6.8915, 'longitude' => 107.6107]);
            foreach ($enrollments as $name => $enrollment) {
                StudentAttendance::create(['class_session_id' => $session->id, 'student_id' => $enrollment->student_id, 'is_present' => ! ($name === 'Joko' && $index === 1)]);
                if ($index > 0) {
                    ProgressNote::create(['class_session_id' => $session->id, 'student_id' => $enrollment->student_id, 'trainer_id' => $trainer->id, 'note' => "$name aktif bertanya dan mulai memahami perulangan."]);
                }
            }
        }
        // Sesi hari ini: belum ada check-in trainer maupun absensi siswa. Jam dibuat lebar supaya demo tidak terkunci oleh jam.
        ClassSession::create(['class_group_id' => $group->id, 'trainer_id' => $rina->id, 'mode' => 'onsite', 'date' => today()->toDateString(), 'start_time' => '06:00', 'end_time' => '21:00', 'meeting_no' => 4]);
    }

    /** Hana: raport terkirim. Ilham: menunggu tinjauan koordinator. Joko: masih draft trainer. */
    private function reports(array $enrollments, Program $program, User $rina, User $koordinator): void
    {
        $criteria = $program->rubricCriteria()->orderBy('id')->get();
        $matrix = ['Hana' => [[9, 8, 9], 'terkirim'], 'Ilham' => [[7, 8, 7], 'ditinjau'], 'Joko' => [[6, 7, 8], 'draft']];
        foreach ($matrix as $name => [$scores, $status]) {
            $evaluation = Evaluation::create(['enrollment_id' => $enrollments[$name]->id, 'period' => now()->format('Y-m'), 'evaluator_id' => $rina->id, 'summary' => "$name menunjukkan perkembangan baik pada logika dan kerja sama tim."]);
            foreach ($criteria as $index => $criterion) {
                $evaluation->criteria()->attach($criterion->id, ['score' => $scores[$index]]);
            }
            $evaluation->report()->create([
                'status' => $status, 'reviewed_by' => $status === 'terkirim' ? $koordinator->id : null, 'sent_at' => $status === 'terkirim' ? now() : null,
            ]);
        }
    }
}
