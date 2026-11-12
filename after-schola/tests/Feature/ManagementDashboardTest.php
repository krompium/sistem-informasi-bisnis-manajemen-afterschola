<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Registration;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Dashboard Management (MGT-60 sampai 62; AC-14, AC-08): KPI (siswa aktif, income, retensi, okupansi), funnel, dan panel operasional.
 * Rumus mengikuti PRD 6.3; seluruh angka dibatasi scope cabang (BR-19) dan permission "view dashboard".
 */
class ManagementDashboardTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private const PERIOD = ['from' => '2026-11-01', 'to' => '2026-11-30'];

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 12:00:00');
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgramWithLevel();
        $this->leader = $this->makeStaff('management', 'assigned', [$this->bdg->id], ['view dashboard']);
        Sanctum::actingAs($this->leader);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- fixtures ----

    private function classGroup(Branch $branch, int $capacity = 10, bool $active = true): ClassGroup
    {
        return ClassGroup::create([
            'program_id' => $this->program->id, 'level_id' => $this->program->levels()->first()->id, 'branch_id' => $branch->id,
            'name' => 'Kelas '.uniqid(), 'capacity' => $capacity, 'is_active' => $active,
        ]);
    }

    /** Siswa yang sudah terdaftar dan ditempatkan; $dates menimpa start_date, end_date, dan status enrollment. */
    private function pupil(Branch $branch, string $name, ClassGroup $group, array $dates = []): array
    {
        $child = $this->makeChild($this->makeParent("Ibu $name"), $branch, $this->program, 'evaluated', $name);
        $registration = $this->registrationFor($child, 'terverifikasi');
        $enrollment = $this->enrollStudent($registration, $group);
        $enrollment->update($dates + ['start_date' => '2026-10-01']);

        return [$registration->fresh(), $enrollment];
    }

    private function renewal(Registration $base, string $invoiceStatus): Registration
    {
        $renewal = Registration::create([
            'lead_id' => $base->lead_id, 'branch_id' => $base->branch_id, 'program_id' => $base->program_id, 'student_id' => $base->student_id,
            'student_name' => $base->student_name, 'origin_school' => $base->origin_school, 'level' => $base->level,
            'parent_name' => $base->parent_name, 'parent_phone' => $base->parent_phone, 'parent_email' => $base->parent_email,
            'type' => 'perpanjangan', 'status' => 'terverifikasi',
        ]);
        $this->invoiceFor($renewal, $invoiceStatus);

        return $renewal;
    }

    private function payment(Registration $registration, int $amount, string $status = 'confirmed', string $confirmedAt = '2026-11-10 03:00:00'): Payment
    {
        $invoice = $registration->invoices()->first() ?? $this->invoiceFor($registration, 'paid');

        return Payment::create([
            'invoice_id' => $invoice->id, 'amount' => $amount, 'method' => 'transfer', 'paid_date' => '2026-11-10', 'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? Carbon::parse($confirmedAt, 'UTC') : null,
        ]);
    }

    private function kpi(array $query = []): array
    {
        return $this->getJson('/api/dashboard/kpi?'.http_build_query(self::PERIOD + $query))->assertOk()->json('data');
    }

    /** Data KPI Bandung (hitungan manual ada di test) ditambah dua siswa dan satu pembayaran Jakarta sebagai pengganggu. */
    private function seedKpiData(): void
    {
        $a = $this->classGroup($this->bdg, 10);
        $b = $this->classGroup($this->bdg, 10);
        $this->classGroup($this->bdg, 50, false); // kelas nonaktif: kapasitasnya tidak dihitung

        [$s1] = $this->pupil($this->bdg, 'S1', $a);
        [$s2] = $this->pupil($this->bdg, 'S2', $b, ['start_date' => '2026-11-10']);
        [$s3] = $this->pupil($this->bdg, 'S3', $a, ['start_date' => '2026-09-01', 'end_date' => '2026-11-05', 'status' => 'selesai']);
        [$s4] = $this->pupil($this->bdg, 'S4', $b, ['start_date' => '2026-09-01', 'end_date' => '2026-11-12', 'status' => 'selesai']);
        [$s5] = $this->pupil($this->bdg, 'S5', $a, ['start_date' => '2026-09-01', 'end_date' => '2026-10-20', 'status' => 'selesai']);
        $this->pupil($this->bdg, 'S6', $a, ['start_date' => '2026-12-02']); // mulai setelah akhir periode

        $this->renewal($s3, 'paid');   // berakhir di periode, perpanjangan lunas
        $this->renewal($s4, 'unpaid'); // berakhir di periode, perpanjangan belum lunas
        $this->renewal($s5, 'paid');   // berakhir sebelum periode: tidak dihitung

        $this->payment($s1, 500000);
        $this->payment($s2, 300000, 'confirmed', '2026-11-20 03:00:00');
        $this->payment($s2, 400000, 'pending');
        $this->payment($s1, 100000, 'confirmed', '2026-10-31 03:00:00'); // sebelum periode

        $j = $this->classGroup($this->jkt, 100);
        [$j1] = $this->pupil($this->jkt, 'J1', $j);
        $this->pupil($this->jkt, 'J2', $j);
        $this->payment($j1, 900000);
    }

    // ---- KPI ----

    public function test_kpi_formulas_match_manual_calculation_for_the_users_branch(): void
    {
        $this->seedKpiData();

        $kpi = $this->kpi();

        $this->assertEquals(2, $kpi['students_active']['value']);          // S1 dan S2 aktif pada 30 Nov
        $this->assertEquals(800000, $kpi['income']['value']);              // 500.000 + 300.000 terkonfirmasi dalam periode
        $this->assertEquals(1, $kpi['retention']['numerator']);            // S3 punya perpanjangan lunas
        $this->assertEquals(2, $kpi['retention']['denominator']);          // S3 dan S4 berakhir di periode
        $this->assertEquals(50, $kpi['retention']['value']);
        $this->assertEquals(2, $kpi['occupancy']['numerator']);
        $this->assertEquals(20, $kpi['occupancy']['denominator']);         // dua kelas aktif x kapasitas 10
        $this->assertEquals(10, $kpi['occupancy']['value']);
    }

    public function test_scope_all_sums_every_branch_and_branch_filter_narrows_it(): void
    {
        $this->seedKpiData();
        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['view dashboard']));

        $all = $this->kpi();
        $this->assertEquals(4, $all['students_active']['value']);
        $this->assertEquals(1700000, $all['income']['value']);
        $this->assertEquals(3.3, $all['occupancy']['value']);              // 4 / (20 + 100)
        $this->assertEquals(50, $all['retention']['value']);

        $jakarta = $this->kpi(['branch_id' => $this->jkt->id]);
        $this->assertEquals(2, $jakarta['students_active']['value']);
        $this->assertEquals(900000, $jakarta['income']['value']);
        $this->assertEquals(2, $jakarta['occupancy']['value']);
        $this->assertNull($jakarta['retention']['value']);                 // tidak ada enrollment berakhir
    }

    public function test_requesting_a_branch_outside_the_scope_leaks_nothing(): void
    {
        $this->seedKpiData();

        $kpi = $this->kpi(['branch_id' => $this->jkt->id]);

        $this->assertEquals(0, $kpi['students_active']['value']);
        $this->assertEquals(0, $kpi['income']['value']);
        $this->assertNull($kpi['occupancy']['value']);
    }

    public function test_percentages_are_null_when_their_denominator_is_zero(): void
    {
        $empty = $this->makeBranch('Cabang Kosong', 'KSG');
        Sanctum::actingAs($this->makeStaff('management', 'assigned', [$empty->id], ['view dashboard']));

        $kpi = $this->kpi();

        $this->assertEquals(0, $kpi['students_active']['value']);
        $this->assertEquals(0, $kpi['income']['value']);
        $this->assertNull($kpi['retention']['value']);
        $this->assertNull($kpi['occupancy']['value']);
    }

    public function test_default_period_is_the_current_month_until_today(): void
    {
        $period = $this->getJson('/api/dashboard/kpi')->assertOk()->json('data.period');

        $this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-15'], $period);
    }

    public function test_branch_options_only_list_branches_the_user_can_access(): void
    {
        $this->assertSame([$this->bdg->id], array_column($this->kpi()['branches'], 'id'));

        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['view dashboard']));
        $this->assertEqualsCanonicalizing([$this->bdg->id, $this->jkt->id], array_column($this->kpi()['branches'], 'id'));
    }

    public function test_period_end_before_start_is_422(): void
    {
        $this->getJson('/api/dashboard/kpi?from=2026-11-30&to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    // ---- funnel ----

    public function test_funnel_counts_each_stage_by_its_own_event_in_the_period(): void
    {
        $group = $this->classGroup($this->bdg);

        // L1: sampai ditempatkan. Perpanjangannya (terverifikasi dan lunas) tidak boleh ikut hitungan funnel.
        [$l1] = $this->pupil($this->bdg, 'L1', $group, ['start_date' => '2026-11-10']);
        $l1->update(['verified_at' => now()]);
        $this->invoiceFor($l1, 'paid');
        $this->renewal($l1, 'paid')->update(['verified_at' => now()]);

        // L2: lanjut dan terverifikasi, belum bayar, belum ditempatkan.
        $l2 = $this->registrationFor($this->makeChild($this->makeParent('Ibu L2'), $this->bdg, $this->program, 'evaluated', 'L2'), 'terverifikasi');
        $l2->update(['verified_at' => now()]);
        $this->invoiceFor($l2, 'unpaid');

        $this->decideFor($this->makeChild($this->makeParent('Ibu L3'), $this->bdg, $this->program, 'evaluated', 'L3'), 'tidak');
        $this->makeChild($this->makeParent('Ibu L4'), $this->bdg, $this->program, 'absent', 'L4');
        $this->makeChild($this->makeParent('Ibu L5'), $this->bdg, $this->program, 'scheduled', 'L5');

        // Seluruh jalur di bulan Oktober: tidak ikut periode.
        $oldChild = $this->makeChild($this->makeParent('Ibu Lama'), $this->bdg, $this->program, 'evaluated', 'Lama');
        $old = $this->registrationFor($oldChild, 'terverifikasi');
        $this->invoiceFor($old, 'paid', ['paid_at' => '2026-10-25 03:00:00']);
        $old->update(['verified_at' => '2026-10-20 03:00:00']);
        Lead::whereKey($oldChild->id)->update(['created_at' => '2026-10-20 03:00:00']); // created_at tidak mass-assignable
        $oldChild->trialEvaluation->update(['trial_date' => '2026-10-18']);
        $old->decision->update(['decided_at' => '2026-10-19']);
        $this->enrollStudent($old, $group)->update(['start_date' => '2026-10-26']);

        // Jakarta: pengganggu.
        $jktRegistration = $this->registrationFor($this->makeChild($this->makeParent('Ibu J'), $this->jkt, $this->program, 'evaluated', 'J'), 'terverifikasi');
        $jktRegistration->update(['verified_at' => now()]);

        $stages = collect($this->getJson('/api/dashboard/funnel?'.http_build_query(self::PERIOD))->assertOk()->json('data.stages'))->pluck('count', 'key');

        $this->assertSame(
            ['calon' => 5, 'trial_hadir' => 3, 'lanjut' => 2, 'terverifikasi' => 2, 'lunas' => 1, 'ditempatkan' => 1],
            $stages->all()
        );
    }

    public function test_funnel_counts_events_on_the_last_day_of_the_default_period(): void
    {
        // Periode default berakhir hari ini: keputusan, trial, dan penempatan hari ini harus ikut terhitung.
        $group = $this->classGroup($this->bdg);
        [$registration] = $this->pupil($this->bdg, 'Hari Ini', $group, ['start_date' => today()->toDateString()]);
        $registration->update(['verified_at' => now()]);

        $stages = collect($this->getJson('/api/dashboard/funnel')->assertOk()->json('data.stages'))->pluck('count', 'key');

        $this->assertSame(1, $stages['calon']);
        $this->assertSame(1, $stages['trial_hadir']); // trial_date = 2 hari lalu
        $this->assertSame(1, $stages['lanjut']);      // decided_at = hari ini
        $this->assertSame(1, $stages['terverifikasi']);
        $this->assertSame(1, $stages['ditempatkan']); // start_date = hari ini
    }

    // ---- panel operasional ----

    public function test_operational_panel_counts_todays_attendance_and_pending_work(): void
    {
        Carbon::setTestNow('2026-11-20 12:00:00');
        $group = $this->classGroup($this->bdg);
        $trainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        [$p1] = $this->pupil($this->bdg, 'P1', $group);
        [$p2] = $this->pupil($this->bdg, 'P2', $group);
        [$p3] = $this->pupil($this->bdg, 'P3', $group);

        $today = ClassSession::create(['class_group_id' => $group->id, 'trainer_id' => $trainer->id, 'date' => '2026-11-20', 'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => 2, 'mode' => 'onsite']);
        $yesterday = ClassSession::create(['class_group_id' => $group->id, 'trainer_id' => $trainer->id, 'date' => '2026-11-19', 'start_time' => '09:00', 'end_time' => '10:30', 'meeting_no' => 1, 'mode' => 'onsite']);
        foreach ([[$today, $p1, true], [$today, $p2, true], [$today, $p3, false], [$yesterday, $p1, true]] as [$session, $registration, $present]) {
            StudentAttendance::create(['class_session_id' => $session->id, 'student_id' => $registration->student_id, 'is_present' => $present]);
        }

        $this->invoiceFor($p1, 'unpaid', ['due_date' => '2026-11-19']); // overdue
        $this->invoiceFor($p2, 'unpaid', ['due_date' => '2026-11-19']); // overdue
        $this->invoiceFor($p3, 'unpaid', ['due_date' => '2026-11-25']); // belum jatuh tempo
        $this->payment($p1, 200000, 'pending');
        $this->payment($p3, 100000, 'confirmed');

        foreach (['W1', 'W2'] as $name) {
            $this->registrationFor($this->makeChild($this->makeParent("Ibu $name"), $this->bdg, $this->program, 'evaluated', $name), 'menunggu_verifikasi');
        }
        $this->invoiceFor($this->registrationFor($this->makeChild($this->makeParent('Ibu Lunas'), $this->bdg, $this->program, 'evaluated', 'Lunas'), 'terverifikasi'), 'paid', ['due_date' => '2026-11-01']);

        // Jakarta: tidak boleh ikut terhitung.
        $jkt = $this->registrationFor($this->makeChild($this->makeParent('Ibu JW'), $this->jkt, $this->program, 'evaluated', 'JW'), 'menunggu_verifikasi');
        $this->invoiceFor($jkt, 'unpaid', ['due_date' => '2026-11-01']);
        $this->payment($jkt, 150000, 'pending');

        $this->getJson('/api/dashboard/panel')->assertOk()->assertJson(['data' => [
            'sessions_today' => 1,
            'students_present_today' => 2,
            'registrations_waiting' => 2,
            'payments_waiting' => 1,
            'invoices_overdue' => 2,
        ]]);
    }

    // ---- export (MGT-63) ----

    /** Isi sheet xlsx sebagai peta label (kolom 1) => nilai (kolom 2). */
    private function xlsxLabelled(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'dash').'.xlsx';
        file_put_contents($path, $content);
        $reader = new Reader;
        $reader->open($path);
        $map = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();
                $map[$cells[0]] = $cells[1] ?? null;
            }
        }
        $reader->close();
        unlink($path);

        return $map;
    }

    public function test_excel_export_lists_period_kpi_funnel_and_panel_for_the_users_branch(): void
    {
        $this->seedKpiData();

        $response = $this->get('/api/exports/dashboard/excel?'.http_build_query(self::PERIOD))->assertOk();

        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('dashboard-20261101-20261130.xlsx', $response->headers->get('Content-Disposition'));
        $rows = $this->xlsxLabelled($response->streamedContent());
        $this->assertSame('01/11/2026 – 30/11/2026', $rows['Periode']);
        $this->assertSame('Semua cabang saya', $rows['Cabang']); // tanpa filter: semua cabang yang boleh diakses user
        $this->assertEquals(2, $rows['Siswa aktif']);
        $this->assertEquals(800000, $rows['Income (Rp)']);
        $this->assertEquals(50, $rows['Retensi (%)']);
        $this->assertEquals(10, $rows['Okupansi (%)']);
        $this->assertArrayHasKey('Calon baru', $rows);
        $this->assertArrayHasKey('Ditempatkan di kelas', $rows);
        $this->assertArrayHasKey('Invoice overdue', $rows);
    }

    public function test_excel_export_follows_the_branch_filter_and_scope(): void
    {
        $this->seedKpiData();
        Sanctum::actingAs($this->makeStaff('management', 'all', [], ['view dashboard']));

        $all = $this->xlsxLabelled($this->get('/api/exports/dashboard/excel?'.http_build_query(self::PERIOD))->streamedContent());
        $jakarta = $this->xlsxLabelled($this->get('/api/exports/dashboard/excel?'.http_build_query(self::PERIOD + ['branch_id' => $this->jkt->id]))->streamedContent());

        $this->assertSame('Semua cabang saya', $all['Cabang']);
        $this->assertEquals(1700000, $all['Income (Rp)']);
        $this->assertSame('Cabang Jakarta', $jakarta['Cabang']);
        $this->assertEquals(900000, $jakarta['Income (Rp)']);
    }

    public function test_pdf_export_downloads_the_report(): void
    {
        $this->seedKpiData();

        $response = $this->get('/api/exports/dashboard/pdf?'.http_build_query(self::PERIOD))->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('dashboard-20261101-20261130.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_export_rejects_an_invalid_period(): void
    {
        $this->get('/api/exports/dashboard/excel?from=2026-11-30&to=2026-11-01', ['Accept' => 'application/json'])->assertUnprocessable();
    }

    // ---- permission ----

    public function test_all_dashboard_endpoints_need_the_view_dashboard_permission(): void
    {
        $withoutPermission = $this->makeStaff('management', 'assigned', [$this->bdg->id]);
        $trainer = $this->makeStaff('trainer', 'assigned', [$this->bdg->id]);
        $parent = $this->makeParent();

        foreach ([$withoutPermission, $trainer, $parent] as $user) {
            Sanctum::actingAs($user);
            foreach (['dashboard/kpi', 'dashboard/funnel', 'dashboard/panel', 'exports/dashboard/excel', 'exports/dashboard/pdf'] as $endpoint) {
                $this->getJson("/api/$endpoint")->assertForbidden();
            }
        }
    }
}
