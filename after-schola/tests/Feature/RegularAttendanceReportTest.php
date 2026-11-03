<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentAttendance;
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
 * Modul 7c (MGT-40..43, KEU-08): rekap dan monitoring absensi reguler untuk Management, Pimpinan, dan Keuangan.
 * Sekarang = 15 Nov 2026 12:00 WIB. Rekap siswa hanya menghitung sesi yang sudah lewat dan sudah dicatat
 * (dikunci atau punya baris absensi); sesi yang belum dicatat tidak menghukum siswa.
 *
 * Data (kelas A, Bandung, trainer T1):
 *   s1 3 Nov dikunci   : Alice hadir, Bella tidak, Dika hadir (Citra belum terdaftar)
 *   s2 5 Nov           : Alice hadir, Bella hadir, Citra tanpa tanda (Dika sudah berakhir 4 Nov)
 *   s3 7 Nov           : belum dicatat sama sekali
 *   s4 30 Nov          : masa depan
 */
class RegularAttendanceReportTest extends TestCase
{
    use MakesBranchFixtures, RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private ClassGroup $classA;

    private ClassGroup $classJ;

    private User $t1;

    private User $t2;

    private Student $alice;

    private Student $bella;

    private Student $citra;

    private Student $dika;

    /** @var array<string, ClassSession> */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00', 'Asia/Jakarta')->utc());
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->classA = $this->classIn($this->bdg, 'Coding A');
        $this->classJ = $this->classIn($this->jkt, 'Coding J');
        $this->t1 = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $this->t2 = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);

        $this->alice = $this->enroll('Alice', $this->classA, '2026-10-01');
        $this->bella = $this->enroll('Bella', $this->classA, '2026-10-01');
        $this->citra = $this->enroll('Citra', $this->classA, '2026-11-04');
        $this->dika = $this->enroll('Dika', $this->classA, '2026-10-01', '2026-11-04', 'selesai');

        $this->s['s1'] = $this->sessionOf($this->classA, $this->t1, '2026-11-03', locked: true);
        $this->s['s2'] = $this->sessionOf($this->classA, $this->t1, '2026-11-05');
        $this->s['s3'] = $this->sessionOf($this->classA, $this->t1, '2026-11-07');
        $this->s['s4'] = $this->sessionOf($this->classA, $this->t1, '2026-11-30');
        $this->mark('s1', $this->alice, true);
        $this->mark('s1', $this->bella, false);
        $this->mark('s1', $this->dika, true);
        $this->mark('s2', $this->alice, true);
        $this->mark('s2', $this->bella, true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function classIn(Branch $branch, string $name): ClassGroup
    {
        $program = Program::create(['name' => 'Program '.$name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);

        return ClassGroup::create(['program_id' => $program->id, 'level_id' => $level->id, 'branch_id' => $branch->id, 'name' => $name, 'capacity' => 20]);
    }

    private function enroll(string $name, ClassGroup $class, string $start, ?string $end = null, string $status = 'aktif'): Student
    {
        $student = Student::create(['name' => $name, 'branch_id' => $class->branch_id]);
        Enrollment::create(['class_group_id' => $class->id, 'student_id' => $student->id, 'start_date' => $start, 'end_date' => $end, 'status' => $status]);

        return $student;
    }

    private function sessionOf(ClassGroup $class, User $trainer, string $date, string $start = '09:00', string $end = '10:30', bool $locked = false): ClassSession
    {
        static $meeting = 0;

        return ClassSession::create([
            'class_group_id' => $class->id, 'trainer_id' => $trainer->id, 'date' => $date, 'start_time' => $start,
            'end_time' => $end, 'meeting_no' => ++$meeting, 'mode' => 'onsite', 'is_locked' => $locked,
        ]);
    }

    private function mark(string $key, Student $student, bool $present): void
    {
        StudentAttendance::create(['class_session_id' => $this->s[$key]->id, 'student_id' => $student->id, 'is_present' => $present]);
    }

    private function checkIn(ClassSession $session, User $trainer, string $status, string $jakartaTime, ?string $photo = 'trainer-attendances/foto.png'): TrainerAttendance
    {
        return TrainerAttendance::create([
            'class_session_id' => $session->id, 'trainer_id' => $trainer->id, 'status' => $status,
            'check_in_at' => Carbon::parse($jakartaTime, 'Asia/Jakarta')->utc(), 'photo_path' => $photo,
            'latitude' => -6.9175, 'longitude' => 107.6191, 'note' => 'ok',
        ]);
    }

    private function monitor(string $scope = 'assigned', ?Branch $branch = null, array $permissions = ['view all attendance']): User
    {
        return $this->makeStaff('management', $scope, $branch ? [$branch->id] : [], $permissions);
    }

    private function period(array $extra = []): string
    {
        return http_build_query($extra + ['from' => '2026-11-01', 'to' => '2026-11-30']);
    }

    private function rows(TestResponse $response): array
    {
        return collect($response->json('data'))->mapWithKeys(fn ($r) => [$r['student']['name'] => [$r['sessions'], $r['present'], $r['absent'], $r['percentage']]])->all();
    }

    public function test_student_recap_counts_only_past_recorded_sessions_where_the_student_was_enrolled(): void
    {
        Sanctum::actingAs($this->monitor('all'));
        $res = $this->getJson('/api/regular-attendance/recap?'.$this->period(['class_group_id' => $this->classA->id]))->assertOk();

        $this->assertEquals([
            'Alice' => [2, 2, 0, 100.0],
            'Bella' => [2, 1, 1, 50.0],
            'Citra' => [1, 0, 1, 0.0],   // baru terdaftar 4 Nov: hanya s2
            'Dika' => [1, 1, 0, 100.0],  // enrollment berakhir 4 Nov: hanya s1
        ], $this->rows($res));
        $res->assertJsonPath('meta.sessions', 2)->assertJsonPath('data.0.class_group.name', 'Coding A');
    }

    public function test_recap_respects_period_scope_and_filters(): void
    {
        $this->enroll('Jaya', $this->classJ, '2026-10-01');
        $sj = $this->sessionOf($this->classJ, $this->t1, '2026-11-04', locked: true);
        StudentAttendance::create(['class_session_id' => $sj->id, 'student_id' => Student::where('name', 'Jaya')->value('id'), 'is_present' => true]);

        // Periode sempit: hanya s2 (5 Nov) di kelas A.
        Sanctum::actingAs($this->monitor('all'));
        $narrow = $this->getJson('/api/regular-attendance/recap?'.$this->period(['from' => '2026-11-05', 'to' => '2026-11-06', 'class_group_id' => $this->classA->id]))->assertOk();
        $this->assertEquals(['Alice' => [1, 1, 0, 100.0], 'Bella' => [1, 1, 0, 100.0], 'Citra' => [1, 0, 1, 0.0]], $this->rows($narrow));

        // Scope cabang: staf Bandung hanya melihat Bandung; filter cabang lain kosong; scope all melihat keduanya.
        Sanctum::actingAs($this->monitor('assigned', $this->bdg));
        $this->assertArrayNotHasKey('Jaya', $this->rows($this->getJson('/api/regular-attendance/recap?'.$this->period())->assertOk()));
        $this->assertSame([], $this->getJson('/api/regular-attendance/recap?'.$this->period(['branch_id' => $this->jkt->id]))->assertOk()->json('data'));

        Sanctum::actingAs($this->monitor('all'));
        $this->assertArrayHasKey('Jaya', $this->rows($this->getJson('/api/regular-attendance/recap?'.$this->period())->assertOk()));
        $this->assertSame(['Jaya'], array_keys($this->rows($this->getJson('/api/regular-attendance/recap?'.$this->period(['branch_id' => $this->jkt->id]))->assertOk())));
    }

    public function test_access_requires_a_monitoring_permission_and_a_valid_period(): void
    {
        $url = '/api/regular-attendance/recap?'.$this->period();

        $this->getJson($url)->assertUnauthorized();
        foreach ([$this->t1, $this->makeStaff('parent'), $this->monitor('all', null, ['manage programs']),
            $this->monitor('all', null, ['export trainer attendance'])] as $denied) {
            Sanctum::actingAs($denied);
            $this->getJson($url)->assertForbidden();
        }

        foreach (['view all attendance', 'manage schedule'] as $permission) {
            Sanctum::actingAs($this->monitor('all', null, [$permission]));
            $this->getJson($url)->assertOk();
        }
        $this->getJson('/api/regular-attendance/recap')->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->getJson('/api/regular-attendance/recap?from=2026-11-30&to=2026-11-01')->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson('/api/regular-attendance/recap?'.$this->period(['branch_id' => 99999]))->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_student_history_lists_each_past_session_with_status_and_is_scoped(): void
    {
        Sanctum::actingAs($this->monitor('assigned', $this->bdg));

        $res = $this->getJson("/api/regular-attendance/students/{$this->bella->id}?".$this->period())->assertOk();
        $this->assertEquals(
            [['2026-11-03', 'tidak_hadir'], ['2026-11-05', 'hadir'], ['2026-11-07', 'belum_dicatat']],
            collect($res->json('data'))->map(fn ($r) => [$r['date'], $r['status']])->all()
        );
        $res->assertJsonPath('data.0.class_group.name', 'Coding A')->assertJsonPath('data.0.meeting_no', $this->s['s1']->meeting_no)
            ->assertJsonPath('student.name', 'Bella');

        // Citra baru terdaftar 4 Nov: s1 tidak ikut. Siswa cabang lain: 404.
        $this->assertSame(['2026-11-05', '2026-11-07'], $this->getJson("/api/regular-attendance/students/{$this->citra->id}?".$this->period())->json('data.*.date'));
        $jaya = $this->enroll('Jaya', $this->classJ, '2026-10-01');
        $this->getJson("/api/regular-attendance/students/{$jaya->id}?".$this->period())->assertNotFound();
    }

    public function test_trainer_recap_lists_sessions_with_check_in_details_and_summarises_per_trainer(): void
    {
        $this->checkIn($this->s['s1'], $this->t1, 'hadir', '2026-11-03 08:55');
        $this->checkIn($this->s['s2'], $this->t1, 'terlambat', '2026-11-05 09:20', null);
        // s3: pelaksana T1 tidak check-in. Hari ini 09:00 (sudah mulai) milik T2 tanpa check-in; 18:00 belum mulai.
        $todayStarted = $this->sessionOf($this->classA, $this->t2, '2026-11-15', '09:00', '10:30');
        $this->sessionOf($this->classA, $this->t2, '2026-11-15', '18:00', '19:00');

        Sanctum::actingAs($this->monitor('all', null, ['export trainer attendance']));
        $res = $this->getJson('/api/regular-attendance/trainers?'.$this->period())->assertOk();

        $rows = collect($res->json('data'))->keyBy('session_id');
        $this->assertCount(4, $rows);                                  // s1, s2, s3, sesi hari ini yang sudah mulai
        $this->assertSame('hadir', $rows[$this->s['s1']->id]['status']);
        $this->assertSame('2026-11-03 08:55', Carbon::parse($rows[$this->s['s1']->id]['check_in_at'])->timezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertStringContainsString('trainer-attendances/foto.png', $rows[$this->s['s1']->id]['photo_url']);
        $this->assertEquals(-6.9175, $rows[$this->s['s1']->id]['latitude']);
        $this->assertSame('terlambat', $rows[$this->s['s2']->id]['status']);
        $this->assertNull($rows[$this->s['s2']->id]['photo_url']);
        $this->assertSame('belum_check_in', $rows[$this->s['s3']->id]['status']);
        $this->assertNull($rows[$this->s['s3']->id]['check_in_at']);
        $this->assertSame('belum_check_in', $rows[$todayStarted->id]['status']);

        $summary = collect($res->json('summary'))->mapWithKeys(fn ($r) => [$r['trainer']['id'] => [$r['sessions'], $r['hadir'], $r['terlambat'], $r['tidak_hadir'], $r['belum_check_in']]]);
        $this->assertEquals([$this->t1->id => [3, 1, 1, 0, 1], $this->t2->id => [1, 0, 0, 0, 1]], $summary->all());

        // Filter trainer, dan scope cabang untuk Keuangan yang dibatasi cabang lain.
        $this->assertCount(1, $this->getJson('/api/regular-attendance/trainers?'.$this->period(['trainer_id' => $this->t2->id]))->json('data'));
        Sanctum::actingAs($this->monitor('assigned', $this->jkt, ['export trainer attendance']));
        $this->assertSame([], $this->getJson('/api/regular-attendance/trainers?'.$this->period())->assertOk()->json('data'));
    }

    public function test_trainers_who_have_not_checked_in_today_are_listed_for_started_sessions_only(): void
    {
        $checkedIn = $this->sessionOf($this->classA, $this->t1, '2026-11-15', '08:00', '09:30');
        $this->checkIn($checkedIn, $this->t1, 'hadir', '2026-11-15 07:55');
        $missing = $this->sessionOf($this->classA, $this->t2, '2026-11-15', '09:00', '10:30');
        $this->sessionOf($this->classA, $this->t2, '2026-11-15', '18:00', '19:00');                 // belum mulai
        $this->sessionOf($this->classA, $this->t2, '2026-11-14', '09:00', '10:30');                 // kemarin
        $jakartaSession = $this->sessionOf($this->classJ, $this->t1, '2026-11-15', '09:00', '10:30'); // cabang lain

        Sanctum::actingAs($this->monitor('assigned', $this->bdg));
        $res = $this->getJson('/api/regular-attendance/trainers/missing-today')->assertOk();

        $this->assertEquals([$missing->id], $res->json('data.*.session_id'));
        $res->assertJsonPath('data.0.trainer.name', $this->t2->name)->assertJsonPath('data.0.class_group.name', 'Coding A');

        Sanctum::actingAs($this->monitor('all'));
        $this->assertEqualsCanonicalizing([$missing->id, $jakartaSession->id], $this->getJson('/api/regular-attendance/trainers/missing-today')->json('data.*.session_id'));

        Sanctum::actingAs($this->t1);
        $this->getJson('/api/regular-attendance/trainers/missing-today')->assertForbidden();
    }

    public function test_student_recap_exports_to_excel_and_pdf(): void
    {
        Sanctum::actingAs($this->monitor('all'));
        $query = $this->period(['class_group_id' => $this->classA->id]);

        $cells = $this->xlsxCells($this->get("/api/exports/regular-attendance/excel?{$query}")->assertOk());
        $this->assertContains('Alice', $cells);
        $this->assertContains('Citra', $cells);
        $this->assertContains('Coding A', $cells);

        $pdf = $this->get("/api/exports/regular-attendance/pdf?{$query}")->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->getJson('/api/exports/regular-attendance/excel')->assertStatus(422);
        Sanctum::actingAs($this->monitor('all', null, ['export trainer attendance']));
        $this->get("/api/exports/regular-attendance/excel?{$query}")->assertForbidden();
        Sanctum::actingAs($this->t1);
        $this->get("/api/exports/regular-attendance/pdf?{$query}")->assertForbidden();
    }

    public function test_trainer_recap_exports_to_excel_for_finance_within_branch_scope(): void
    {
        $this->checkIn($this->s['s1'], $this->t1, 'hadir', '2026-11-03 08:55');
        $jakarta = $this->sessionOf($this->classJ, $this->t1, '2026-11-04');

        Sanctum::actingAs($this->monitor('assigned', $this->bdg, ['export trainer attendance']));
        $cells = $this->xlsxCells($this->get('/api/exports/regular-attendance/trainers/excel?'.$this->period())->assertOk());

        $this->assertContains($this->t1->name, $cells);
        $this->assertContains('Coding A', $cells);
        $this->assertContains('Hadir', $cells);
        $this->assertContains('Belum check-in', $cells);   // s2 dan s3 tanpa check-in
        $this->assertNotContains('Coding J', $cells);      // cabang lain

        $this->getJson('/api/exports/regular-attendance/trainers/excel')->assertStatus(422);
        Sanctum::actingAs($this->makeStaff('parent'));
        $this->get('/api/exports/regular-attendance/trainers/excel?'.$this->period())->assertForbidden();
        $this->assertNotNull($jakarta->id);
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
