<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TrainerAssignment;
use App\Models\TrainerAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\MakesBranchFixtures;
use Tests\TestCase;

/**
 * Modul 7d (TRN-11, TRN-12): trainer melihat rekap kehadirannya sendiri dan riwayat absensi kelasnya.
 * Sekarang = 15 Nov 2026 12:00 WIB.
 *
 *   Kelas A (T1 utama, T2 asisten): a1 3 Nov T1 dikunci (Alice hadir, Bella tidak, check-in T1 hadir);
 *     a2 5 Nov T2 (belum dicatat); a3 7 Nov T1 (tanpa check-in); a4 30 Nov T1 (masa depan)
 *   Kelas B (T3 utama): b1 4 Nov dijalankan T1 sebagai pengganti (check-in terlambat); b2 6 Nov T3
 *   Siswa: Alice, Bella sejak 1 Okt; Citra sejak 4 Nov (kelas A)
 */
class TrainerOwnRecapTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private ClassGroup $classA;

    private ClassGroup $classB;

    private User $t1;

    private User $t2;

    private User $t3;

    /** @var array<string, ClassSession> */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00', 'Asia/Jakarta')->utc());
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->classA = $this->classIn('Coding A');
        $this->classB = $this->classIn('Coding B');
        [$this->t1, $this->t2, $this->t3] = [$this->trainer(), $this->trainer(), $this->trainer()];
        foreach ([[$this->t1, 'Trainer Satu'], [$this->t2, 'Trainer Dua'], [$this->t3, 'Trainer Tiga']] as [$user, $name]) {
            $user->update(['name' => $name]); // nama berbeda agar kebocoran data trainer lain terdeteksi
        }
        TrainerAssignment::create(['class_group_id' => $this->classA->id, 'trainer_id' => $this->t1->id, 'role' => 'utama']);
        TrainerAssignment::create(['class_group_id' => $this->classA->id, 'trainer_id' => $this->t2->id, 'role' => 'asisten']);
        TrainerAssignment::create(['class_group_id' => $this->classB->id, 'trainer_id' => $this->t3->id, 'role' => 'utama']);

        $alice = $this->enroll('Alice', $this->classA, '2026-10-01');
        $bella = $this->enroll('Bella', $this->classA, '2026-10-01');
        $this->enroll('Citra', $this->classA, '2026-11-04');

        $this->s['a1'] = $this->sessionOf($this->classA, $this->t1, '2026-11-03', locked: true);
        $this->s['a2'] = $this->sessionOf($this->classA, $this->t2, '2026-11-05');
        $this->s['a3'] = $this->sessionOf($this->classA, $this->t1, '2026-11-07');
        $this->s['a4'] = $this->sessionOf($this->classA, $this->t1, '2026-11-30');
        $this->s['b1'] = $this->sessionOf($this->classB, $this->t1, '2026-11-04');
        $this->s['b2'] = $this->sessionOf($this->classB, $this->t3, '2026-11-06');
        StudentAttendance::create(['class_session_id' => $this->s['a1']->id, 'student_id' => $alice->id, 'is_present' => true]);
        StudentAttendance::create(['class_session_id' => $this->s['a1']->id, 'student_id' => $bella->id, 'is_present' => false]);
        $this->checkIn('a1', $this->t1, 'hadir');
        $this->checkIn('b1', $this->t1, 'terlambat');
        $this->checkIn('b2', $this->t3, 'hadir');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function classIn(string $name): ClassGroup
    {
        $program = Program::create(['name' => 'Program '.$name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);

        return ClassGroup::create(['program_id' => $program->id, 'level_id' => $level->id, 'branch_id' => $this->bdg->id, 'name' => $name, 'capacity' => 20]);
    }

    private function trainer(): User
    {
        return $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
    }

    private function enroll(string $name, ClassGroup $class, string $start): Student
    {
        $student = Student::create(['name' => $name, 'branch_id' => $this->bdg->id]);
        Enrollment::create(['class_group_id' => $class->id, 'student_id' => $student->id, 'start_date' => $start, 'status' => 'aktif']);

        return $student;
    }

    private function sessionOf(ClassGroup $class, User $trainer, string $date, bool $locked = false): ClassSession
    {
        static $meeting = 0;

        return ClassSession::create([
            'class_group_id' => $class->id, 'trainer_id' => $trainer->id, 'date' => $date, 'start_time' => '09:00',
            'end_time' => '10:30', 'meeting_no' => ++$meeting, 'mode' => 'onsite', 'is_locked' => $locked,
        ]);
    }

    private function checkIn(string $key, User $trainer, string $status): void
    {
        TrainerAttendance::create([
            'class_session_id' => $this->s[$key]->id, 'trainer_id' => $trainer->id, 'status' => $status,
            'check_in_at' => Carbon::now(), 'photo_path' => 'trainer-attendances/x.png', 'latitude' => -6.9, 'longitude' => 107.6,
        ]);
    }

    private function period(array $extra = []): string
    {
        return http_build_query($extra + ['from' => '2026-11-01', 'to' => '2026-11-30']);
    }

    public function test_own_recap_lists_only_sessions_the_trainer_ran_with_their_check_in_status(): void
    {
        Sanctum::actingAs($this->t1);

        $res = $this->getJson('/api/trainer/recap?'.$this->period())->assertOk();

        $statuses = collect($res->json('data'))->mapWithKeys(fn ($r) => [$r['session_id'] => $r['status']])->all();
        $this->assertEquals([
            $this->s['a1']->id => 'hadir',
            $this->s['b1']->id => 'terlambat',       // pengganti di kelas yang bukan penugasannya
            $this->s['a3']->id => 'belum_check_in',
        ], $statuses);                                  // a2 (T2), b2 (T3), dan a4 (masa depan) tidak ikut

        $this->assertEquals([[3, 1, 1, 0, 1]], collect($res->json('summary'))->map(fn ($r) => [$r['sessions'], $r['hadir'], $r['terlambat'], $r['tidak_hadir'], $r['belum_check_in']])->all());
        $this->assertSame($this->t1->id, $res->json('summary.0.trainer.id'));
        $this->assertStringContainsString('trainer-attendances/x.png', collect($res->json('data'))->firstWhere('session_id', $this->s['a1']->id)['photo_url']);
    }

    public function test_own_recap_never_leaks_other_trainers_and_respects_period_and_class_filter(): void
    {
        Sanctum::actingAs($this->t2);
        $this->assertEquals([$this->s['a2']->id], $this->getJson('/api/trainer/recap?'.$this->period())->assertOk()->json('data.*.session_id'));

        Sanctum::actingAs($this->t1);
        $this->assertEquals([$this->s['a1']->id], $this->getJson('/api/trainer/recap?'.$this->period(['from' => '2026-11-03', 'to' => '2026-11-03']))->json('data.*.session_id'));
        $this->assertEquals([$this->s['b1']->id], $this->getJson('/api/trainer/recap?'.$this->period(['class_group_id' => $this->classB->id]))->json('data.*.session_id'));

        $this->getJson('/api/trainer/recap')->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->getJson('/api/trainer/recap?from=2026-11-30&to=2026-11-01')->assertStatus(422)->assertJsonValidationErrors('to');
    }

    public function test_trainer_endpoints_are_for_trainers_only(): void
    {
        $urls = ['/api/trainer/recap?'.$this->period(), '/api/trainer/class-history?'.$this->period(), '/api/exports/trainer/recap/excel?'.$this->period()];
        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }

        foreach ([$this->makeStaff('management', 'all', [], ['view all attendance', 'manage schedule']), $this->makeStaff('parent')] as $user) {
            Sanctum::actingAs($user);
            foreach ($urls as $url) {
                $this->getJson($url)->assertForbidden();
            }
        }
    }

    public function test_class_history_shows_sessions_of_my_classes_and_sessions_i_ran_with_counts(): void
    {
        Sanctum::actingAs($this->t1);
        $res = $this->getJson('/api/trainer/class-history?'.$this->period())->assertOk();

        // Terbaru dulu. a4 (masa depan), b2 (kelas B milik T3) tidak ikut; b1 ikut karena T1 pelaksananya.
        $this->assertEquals(
            [$this->s['a3']->id, $this->s['a2']->id, $this->s['b1']->id, $this->s['a1']->id],
            $res->json('data.*.session_id')
        );
        $byId = collect($res->json('data'))->keyBy('session_id');
        $this->assertSame(['dicatat', 1, 2, true], [$byId[$this->s['a1']->id]['status'], $byId[$this->s['a1']->id]['present_count'], $byId[$this->s['a1']->id]['total'], $byId[$this->s['a1']->id]['is_locked']]);
        $this->assertSame(['belum_dicatat', 0, 3], [$byId[$this->s['a2']->id]['status'], $byId[$this->s['a2']->id]['present_count'], $byId[$this->s['a2']->id]['total']]); // Citra ikut sejak 4 Nov
        $this->assertSame($this->t2->name, $byId[$this->s['a2']->id]['trainer']['name']);
        $this->assertSame('Coding A', $byId[$this->s['a1']->id]['class_group']['name']);
        $this->assertSame($this->s['a1']->meeting_no, $byId[$this->s['a1']->id]['meeting_no']);

        $this->assertEquals([$this->s['b1']->id], $this->getJson('/api/trainer/class-history?'.$this->period(['class_group_id' => $this->classB->id]))->json('data.*.session_id'));
    }

    public function test_class_history_is_isolated_per_trainer(): void
    {
        Sanctum::actingAs($this->t3);
        $this->assertEquals([$this->s['b2']->id, $this->s['b1']->id], $this->getJson('/api/trainer/class-history?'.$this->period())->assertOk()->json('data.*.session_id'));
        // Kelas A bukan miliknya: filter tidak membukanya.
        $this->assertSame([], $this->getJson('/api/trainer/class-history?'.$this->period(['class_group_id' => $this->classA->id]))->json('data'));

        Sanctum::actingAs($this->trainer());
        $this->assertSame([], $this->getJson('/api/trainer/class-history?'.$this->period())->assertOk()->json('data'));
    }

    public function test_own_recap_exports_to_excel_without_other_trainers_data(): void
    {
        Sanctum::actingAs($this->t1);
        $cells = $this->xlsxCells($this->get('/api/exports/trainer/recap/excel?'.$this->period())->assertOk());

        $this->assertContains('Coding A', $cells);
        $this->assertContains('Coding B', $cells);
        $this->assertContains($this->t1->name, $cells);
        $this->assertContains('Terlambat', $cells);
        $this->assertContains('Belum check-in', $cells);
        $this->assertNotContains($this->t2->name, $cells);
        $this->assertNotContains($this->t3->name, $cells);

        $this->getJson('/api/exports/trainer/recap/excel')->assertStatus(422);
    }

    private function xlsxCells(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $reader = new Reader;
        $reader->open($path);
        $cells = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                array_push($cells, ...$row->toArray());
            }
        }
        $reader->close();
        unlink($path);

        return $cells;
    }
}
