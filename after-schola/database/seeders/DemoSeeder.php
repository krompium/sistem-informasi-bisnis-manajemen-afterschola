<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\EnrollmentDecision;
use App\Models\ExpoReport;
use App\Models\HotIssue;
use App\Models\Program;
use App\Models\Registration;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TrainerAttendance;
use App\Models\TrialEvaluation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for every feature currently on `main`.
 * Idempotent (safe to run twice) and date-relative: "today" sessions always use today().
 *
 * Run:  php artisan db:seed --class=DemoSeeder
 * Accounts (password: "password"): admin@afterschola.id, trainer@afterschola.id,
 * trainer2@afterschola.id, trainer3@afterschola.id
 */
class DemoSeeder extends Seeder
{
    private const NAMES = [
        'Andi Pratama', 'Budi Santoso', 'Citra Lestari', 'Dewi Anggraini', 'Eka Putra', 'Fajar Nugraha',
        'Gita Permata', 'Hana Safitri', 'Indra Wijaya', 'Joko Saputra', 'Kirana Dewi', 'Lukman Hakim',
        'Maya Sari', 'Naufal Rizky', 'Olivia Putri', 'Putra Mahendra', 'Qori Amalia', 'Rafi Ramadhan',
        'Salsa Aulia', 'Tegar Firmansyah', 'Umi Kulsum', 'Vina Oktaviani', 'Wahyu Hidayat', 'Yasmin Zahra',
        'Zaki Maulana', 'Alya Nabila', 'Bagas Kurniawan', 'Chandra Adi', 'Dinda Maharani', 'Elang Prakoso',
        'Farah Kamila', 'Galih Setiawan', 'Hilmi Fauzan', 'Intan Permatasari', 'Jihan Azzahra', 'Kevin Aditya',
        'Laila Rahma', 'Miko Alfarizi', 'Nadia Khairunnisa', 'Oka Wiratama', 'Pita Maharani', 'Rangga Saputra',
        'Sinta Bella', 'Taufik Hidayat', 'Ulfa Nurhaliza', 'Vito Ramadhan', 'Winda Lestari', 'Xavier Dani',
        'Yuda Prasetya', 'Zahra Amelia', 'Arga Dwi', 'Bella Safira', 'Cakra Wijaya', 'Dian Anggun',
        'Erlangga Putra', 'Fitri Handayani', 'Gilang Ramadhan', 'Hesti Purnama', 'Irfan Maulana', 'Jelita Sari',
    ];

    public function run(): void
    {
        // Base accounts, school "SMP Contoh 1" and its two classrooms (idempotent).
        $this->call(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@afterschola.id')->firstOrFail();
        $trainers = $this->seedTrainers();
        $schools = $this->seedSchools($trainers);
        $classrooms = $this->seedClassrooms($schools);
        $this->seedStudents($schools, $classrooms, $trainers);
        $this->seedSessions($schools, $classrooms, $trainers);
        $this->seedExpoReports($schools, $trainers);
        $this->seedHotIssues($admin);
        $programs = $this->seedPrograms();
        $this->seedTrialFunnel($programs, $trainers, $admin);

        // Modul 12 (team, proyek, task) has its own seeder; run it only if it exists in this checkout.
        if (class_exists(ProjectManagementSeeder::class)) {
            $this->call(ProjectManagementSeeder::class);
        }
    }

    /** @return array<int, User> keyed 1..3 */
    private function seedTrainers(): array
    {
        $trainers = [1 => User::where('email', 'trainer@afterschola.id')->firstOrFail()];
        $trainers[1]->update([
            'subjects_taught' => ['Coding', 'Robotik'],
            'levels_taught' => ['beginner', 'intermediate'],
        ]);

        $extra = [
            2 => ['trainer2@afterschola.id', 'Trainer Dua', ['Coding'], ['intermediate']],
            3 => ['trainer3@afterschola.id', 'Trainer Tiga', ['Robotik', 'Public Speaking'], ['beginner', 'intermediate']],
        ];
        foreach ($extra as $i => [$email, $name, $subjects, $levels]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'is_active' => true],
            );
            $user->update(['subjects_taught' => $subjects, 'levels_taught' => $levels]);
            $user->syncRoles('trainer');
            $trainers[$i] = $user;
        }

        return $trainers;
    }

    /** @return array<int, School> keyed 1..3 */
    private function seedSchools(array $trainers): array
    {
        $schools = [1 => School::where('name', 'SMP Contoh 1')->firstOrFail()];
        $schools[2] = School::firstOrCreate(
            ['name' => 'SMP Contoh 2'],
            ['address' => 'Jl. Anggrek No. 7, Bandung', 'pic_name' => 'Pak Rahmat', 'pic_phone' => '081234500002'],
        );
        $schools[3] = School::firstOrCreate(
            ['name' => 'SMA Contoh 3'],
            ['address' => 'Jl. Kenanga No. 21, Bandung', 'pic_name' => 'Bu Dewi', 'pic_phone' => '081234500003'],
        );

        // Many-to-many on purpose: trainer 1 cannot see school 3 (isolation demo).
        $trainers[1]->assignedSchools()->syncWithoutDetaching([$schools[1]->id, $schools[2]->id]);
        $trainers[2]->assignedSchools()->syncWithoutDetaching([$schools[2]->id, $schools[3]->id]);
        $trainers[3]->assignedSchools()->syncWithoutDetaching([$schools[3]->id]);

        return $schools;
    }

    /** @return array<int, array<string, Classroom>> [schoolIndex][levelName] */
    private function seedClassrooms(array $schools): array
    {
        $result = [];
        foreach ($schools as $i => $school) {
            foreach (['Beginner', 'Intermediate'] as $level) {
                $result[$i][$level] = Classroom::firstOrCreate(
                    ['school_id' => $school->id, 'name' => $level],
                    ['level' => $level],
                );
            }
        }

        return $result;
    }

    private function seedStudents(array $schools, array $classrooms, array $trainers): void
    {
        $origin = ['7A', '7B', '8A', '8B', '9A'];
        $cursor = 0;

        foreach ($schools as $i => $school) {
            $perLevel = $i === 1 ? 8 : 6;
            foreach ($classrooms[$i] as $levelName => $classroom) {
                for ($n = 0; $n < $perLevel; $n++) {
                    Student::firstOrCreate(
                        ['classroom_id' => $classroom->id, 'name' => self::NAMES[$cursor % count(self::NAMES)]],
                        [
                            'school_id' => $school->id,
                            'origin_class' => $origin[$n % count($origin)],
                            'level' => strtolower($levelName),
                            'created_by' => $trainers[$i === 3 ? 3 : 1]->id,
                        ],
                    );
                    $cursor++;
                }
            }
        }
    }

    private function seedSessions(array $schools, array $classrooms, array $trainers): void
    {
        // Which trainer teaches which (school, level).
        $trainerFor = [
            1 => ['Beginner' => 1, 'Intermediate' => 1],
            2 => ['Beginner' => 1, 'Intermediate' => 2],
            3 => ['Beginner' => 3, 'Intermediate' => 3],
        ];
        // Sessions that happen today. Live attendance demo = [1][Beginner];
        // trainer already checked in = [2][Intermediate]; never checked in = [3][Beginner].
        $today = ['1:Beginner', '2:Intermediate', '3:Beginner'];

        foreach ($schools as $i => $school) {
            foreach ($classrooms[$i] as $levelName => $classroom) {
                $trainer = $trainers[$trainerFor[$i][$levelName]];
                $mode = ($i === 2 && $levelName === 'Intermediate') ? 'online' : 'onsite';

                foreach ([1 => -28, 2 => -21, 3 => -14, 4 => -7] as $meetingNo => $offset) {
                    $session = $this->session($school, $classroom, $trainer, $mode, $meetingNo, today()->addDays($offset), true);
                    $this->fillPastAttendance($session, $classroom, $trainer, $i === 1 && $levelName === 'Beginner' && $meetingNo === 3);
                }

                $isToday = in_array("$i:$levelName", $today, true);
                $current = $this->session($school, $classroom, $trainer, $mode, 5, $isToday ? today() : today()->addDays(3), false);
                if ($i === 2 && $levelName === 'Intermediate') {
                    $current->update(['started_at' => now()]);
                    TrainerAttendance::updateOrCreate(
                        ['class_session_id' => $current->id, 'trainer_id' => $trainer->id],
                        ['check_in_at' => now(), 'status' => 'hadir', 'note' => 'Demo: check-in online (tanpa foto)'],
                    );
                }

                $this->session($school, $classroom, $trainer, $mode, 6, today()->addDays(10), false);
            }
        }
    }

    private function session(School $school, Classroom $classroom, User $trainer, string $mode, int $meetingNo, $date, bool $locked): ClassSession
    {
        return ClassSession::updateOrCreate(
            ['school_id' => $school->id, 'classroom_id' => $classroom->id, 'meeting_no' => $meetingNo],
            [
                'trainer_id' => $trainer->id,
                'mode' => $mode,
                'date' => $date,
                'start_time' => '15:30:00',
                'end_time' => '17:00:00',
                'is_locked' => $locked,
                'started_at' => $locked ? $date->copy()->setTime(15, 30) : null,
                'note' => null,
            ],
        );
    }

    private function fillPastAttendance(ClassSession $session, Classroom $classroom, User $trainer, bool $late): void
    {
        // Deterministic ~85% presence, so reruns and exports look the same.
        foreach (Student::where('classroom_id', $classroom->id)->get() as $student) {
            StudentAttendance::updateOrCreate(
                ['class_session_id' => $session->id, 'student_id' => $student->id],
                ['is_present' => crc32($session->id . '-' . $student->id) % 100 < 85, 'note' => null],
            );
        }

        $onsite = $session->mode === 'onsite';
        TrainerAttendance::updateOrCreate(
            ['class_session_id' => $session->id, 'trainer_id' => $trainer->id],
            [
                'check_in_at' => $session->date->copy()->setTime(15, $late ? 50 : 25),
                'status' => $late ? 'terlambat' : 'hadir',
                'photo_path' => null, // demo: no uploaded files; UI should show the empty-photo state
                'latitude' => $onsite ? -6.9175 + ($session->school_id % 5) * 0.003 : null,
                'longitude' => $onsite ? 107.6191 + ($session->school_id % 5) * 0.003 : null,
                'note' => $late ? 'Macet di jalan' : null,
            ],
        );
    }

    private function seedExpoReports(array $schools, array $trainers): void
    {
        $rows = [
            [1, 1, -20, 'Tim Alpha', 5, 'ya', 'Siswa sangat antusias, banyak yang bertanya.', false, null],
            [2, 2, -12, 'Tim Beta', 4, 'sebagian', 'Antusias, tetapi waktu demo terpotong.', true, 'Proyektor sempat bermasalah 10 menit.'],
            [3, 3, -5, 'Tim Gamma', 3, 'ya', 'Cukup antusias, perlu materi lebih interaktif.', false, null],
        ];
        foreach ($rows as [$schoolIdx, $trainerIdx, $offset, $team, $rating, $onSchedule, $enthusiasm, $hasIssue, $issueNote]) {
            ExpoReport::updateOrCreate(
                ['school_id' => $schools[$schoolIdx]->id, 'trainer_id' => $trainers[$trainerIdx]->id, 'team_name' => $team],
                [
                    'date' => today()->addDays($offset),
                    'rating' => $rating,
                    'on_schedule' => $onSchedule,
                    'enthusiasm' => $enthusiasm,
                    'has_issue' => $hasIssue,
                    'issue_note' => $issueNote,
                ],
            );
        }
    }

    private function seedHotIssues(User $admin): void
    {
        $rows = [
            ['Pengumuman jadwal pekan depan', 'Jadwal ngajar pekan depan sudah diperbarui, cek menu Jadwal.', 'info', true],
            ['Absensi maksimal 4 jam setelah mengajar', 'Isi absensi paling lambat 4 jam setelah sesi selesai.', 'warning', true],
            ['Server maintenance Sabtu malam', 'Sistem tidak dapat diakses Sabtu 22.00-23.00.', 'critical', true],
            ['Pengumuman lama (nonaktif)', 'Contoh hot issue yang sudah dinonaktifkan.', 'info', false],
        ];
        foreach ($rows as [$title, $message, $severity, $active]) {
            HotIssue::updateOrCreate(
                ['title' => $title],
                ['message' => $message, 'severity' => $severity, 'is_active' => $active, 'created_by' => $admin->id],
            );
        }
    }

    /** @return array<string, Program> keyed by name */
    private function seedPrograms(): array
    {
        $defs = [
            ['Coding Kids', 'SD', 'offline', 'Dasar pemrograman visual untuk SD-SMP.', 750000, true],
            ['Robotik Online', 'SMP', 'online', 'Robotik dasar via kelas daring.', 500000, true],
            ['Public Speaking', 'SMA', 'offline', 'Latihan berbicara di depan umum.', 450000, false],
        ];
        $programs = [];
        foreach ($defs as [$name, $jenjang, $type, $desc, $fee, $active]) {
            $program = Program::updateOrCreate(
                ['name' => $name],
                ['jenjang' => $jenjang, 'tipe' => $type, 'description' => $desc, 'price' => $fee, 'is_active' => $active],
            );
            $programs[$name] = $program;
        }

        return $programs;
    }

    private function seedTrialFunnel(array $programs, array $trainers, User $admin): void
    {
        // [name, school, class, program, trainer, dayOffset, attendance, scores(4)|null, level, recommendation, decision|null, registration|null]
        $rows = [
            ['Rania Aulia', 'SDN Merdeka 1', '5A', 'Coding Kids', 1, -9, 'hadir', [5, 4, 5, 4], 'beginner', 'lanjut', 'lanjut', 'menunggu_verifikasi'],
            ['Bayu Kusuma', 'SDN Merdeka 2', '6B', 'Coding Kids', 1, -8, 'hadir', [4, 4, 3, 4], 'intermediate', 'lanjut', 'lanjut', 'draft'],
            ['Citra Maharani', 'SMP Harapan', '7C', 'Robotik Online', 2, -6, 'hadir', [4, 3, 4, 4], 'beginner', 'lanjut', null, null],
            ['Dimas Anggara', 'SMP Harapan', '8A', 'Robotik Online', 2, -5, 'hadir', [3, 3, 2, 3], 'beginner', 'ragu', 'pikir_pikir', null],
            ['Elsa Putri', 'SDN Merdeka 1', '4B', 'Coding Kids', 3, -4, 'hadir', [2, 2, 2, 3], 'beginner', 'tidak', 'tidak', null],
            ['Fikri Alamsyah', 'SMP Harapan', '9B', 'Robotik Online', 3, -3, 'tidak_hadir', null, null, null, null, null],
        ];

        foreach ($rows as [$name, $school, $class, $program, $trainerIdx, $offset, $attendance, $scores, $level, $reco, $decision, $regStatus]) {
            $eval = TrialEvaluation::updateOrCreate(
                ['participant_name' => $name],
                [
                    'program_id' => $programs[$program]->id,
                    'origin_school' => $school,
                    'origin_class' => $class,
                    'trainer_id' => $trainers[$trainerIdx]->id,
                    'trial_date' => today()->addDays($offset),
                    'attendance' => $attendance,
                    'score_enthusiasm' => $scores[0] ?? null,
                    'score_understanding' => $scores[1] ?? null,
                    'score_focus' => $scores[2] ?? null,
                    'score_collaboration' => $scores[3] ?? null,
                    'strengths' => $scores ? 'Cepat menangkap instruksi dan aktif bertanya.' : null,
                    'improvements' => $scores ? 'Perlu latihan fokus di sesi yang lebih panjang.' : null,
                    // TODO(nanti): demo funnel masih bentuk lama (tanpa cabang, slot, lead, level program); hanya terlihat scope 'all'.
                    'recommendation' => $reco,
                ],
            );

            if (! $decision) {
                continue;
            }

            $dec = EnrollmentDecision::updateOrCreate(
                ['evaluation_id' => $eval->id],
                [
                    'decision' => $decision,
                    'reason' => match ($decision) {
                        'tidak' => 'Orang tua memilih jadwal lain di luar program.',
                        'pikir_pikir' => 'Menunggu diskusi keluarga.',
                        default => null,
                    },
                    'decided_at' => today()->addDays($offset + 2),
                    'follow_up_date' => $decision === 'pikir_pikir' ? today()->addDays(4) : null,
                    'decided_by' => $admin->id,
                ],
            );

            if ($regStatus) {
                Registration::updateOrCreate(
                    ['decision_id' => $dec->id],
                    [
                        'program_id' => $programs[$program]->id,
                        'student_name' => $name,
                        'birth_date' => today()->subYears(11)->toDateString(),
                        'origin_school' => $school,
                        'origin_class' => $class,
                        'level' => $level,
                        'parent_name' => 'Orang tua ' . $name,
                        'parent_phone' => '0812' . str_pad((string) ($eval->id % 10000), 8, '0', STR_PAD_LEFT),
                        'parent_email' => null,
                        'parent_address' => 'Bandung',
                        'status' => $regStatus,
                        'registered_by' => $admin->id,
                    ],
                );
            }
        }
    }
}
