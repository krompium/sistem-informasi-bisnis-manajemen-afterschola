<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 lanjutan (5c, KEU-07): laporan pemasukan per periode, cabang, program + export Excel/PDF.
 * Income = jumlah payments.amount berstatus confirmed dengan confirmed_at dalam periode (PRD KPI).
 * Batas hari mengikuti Asia/Jakarta; kolom disimpan UTC.
 */
class IncomeReportTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $coding;

    private Program $robotik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->coding = $this->makeProgram(500000);
        $this->robotik = Program::create(['name' => 'Robotik', 'jenjang' => 'SMP', 'tipe' => 'offline', 'price' => 750000, 'is_active' => true]);
    }

    /** Pembayaran terkonfirmasi pada waktu Jakarta tertentu, disimpan UTC seperti aplikasi sesungguhnya. */
    private function income(?Branch $branch, Program $program, string $jakartaTime, string $status = 'confirmed', ?int $amount = null): Payment
    {
        $registration = $this->makeRegistration(
            ['status' => 'terverifikasi', 'branch_id' => $branch?->id, 'student_name' => 'Siswa '.uniqid()],
            $program
        );
        $invoice = $this->makeInvoice($registration, ['amount' => $amount ?? $program->price]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'method' => 'transfer',
            'paid_date' => '2026-10-01',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? Carbon::parse($jakartaTime, 'Asia/Jakarta')->utc() : null,
        ]);
    }

    private function report(array $query): TestResponse
    {
        return $this->getJson('/api/finance/income?'.http_build_query($query));
    }

    public function test_income_counts_only_confirmed_payments_inside_period_using_jakarta_day_boundaries(): void
    {
        $this->income($this->bdg, $this->coding, '2026-10-01 00:10');   // awal periode (UTC masih 30 Sep) -> masuk
        $this->income($this->bdg, $this->robotik, '2026-10-31 23:30');  // akhir periode (UTC 31 Okt 16:30) -> masuk
        $this->income($this->bdg, $this->coding, '2026-11-01 00:30');   // UTC masih 31 Okt, tetapi hari Jakarta 1 Nov -> keluar
        $this->income($this->bdg, $this->coding, '2026-09-30 23:30');   // sebelum periode -> keluar
        $this->income($this->bdg, $this->coding, '2026-10-15 10:00', 'pending');
        $this->income($this->bdg, $this->coding, '2026-10-15 10:00', 'rejected');
        Sanctum::actingAs($this->makeStaff('finance', 'all'));

        $this->report(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.total', 1250000);
    }

    public function test_income_is_grouped_by_program_and_branch_and_can_be_filtered(): void
    {
        $this->income($this->bdg, $this->coding, '2026-10-05 10:00');
        $this->income($this->jkt, $this->coding, '2026-10-06 10:00');
        $this->income($this->jkt, $this->robotik, '2026-10-07 10:00');
        $this->income(null, $this->coding, '2026-10-08 10:00'); // pendaftaran lama tanpa cabang
        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];

        $all = $this->report($period)->assertOk();
        $all->assertJsonPath('data.total', 2250000);
        $byProgram = collect($all->json('data.by_program'))->pluck('total', 'program')->all();
        $this->assertEquals(['Coding Kids' => 1500000, 'Robotik' => 750000], $byProgram);
        $byBranch = collect($all->json('data.by_branch'))->pluck('total', 'branch')->all();
        $this->assertEquals(['Cabang Bandung' => 500000, 'Cabang Jakarta' => 1250000, 'Tanpa cabang' => 500000], $byBranch);

        $this->report($period + ['branch_id' => $this->jkt->id])->assertOk()->assertJsonPath('data.total', 1250000);
        $this->report($period + ['program_id' => $this->robotik->id])->assertOk()->assertJsonPath('data.total', 750000);
    }

    public function test_income_is_scoped_to_user_branches(): void
    {
        $this->income($this->bdg, $this->coding, '2026-10-05 10:00');
        $this->income($this->jkt, $this->coding, '2026-10-06 10:00');
        $this->income(null, $this->coding, '2026-10-07 10:00');
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));
        $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];

        $this->report($period)->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.total', 500000);
        // Filter tidak boleh membuka cabang di luar scope.
        $this->report($period + ['branch_id' => $this->jkt->id])->assertOk()->assertJsonPath('data.count', 0);
    }

    public function test_income_requires_valid_period_and_permission(): void
    {
        $this->getJson('/api/finance/income?from=2026-10-01&to=2026-10-31')->assertUnauthorized();

        Sanctum::actingAs($this->makeStaff('trainer', 'all'));
        $this->report(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertForbidden();

        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $this->report([])->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->report(['from' => '2026-10-31', 'to' => '2026-10-01'])->assertStatus(422)->assertJsonValidationErrors('to');
        $this->report(['from' => '2026-10-01', 'to' => '2026-10-31', 'branch_id' => 99999])->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_excel_export_lists_scoped_payments_with_total(): void
    {
        $mine = $this->income($this->bdg, $this->coding, '2026-10-05 10:00');
        $other = $this->income($this->jkt, $this->robotik, '2026-10-06 10:00');
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));

        $response = $this->get('/api/exports/income/excel?from=2026-10-01&to=2026-10-31')->assertOk();
        $cells = $this->xlsxCells($response);

        $this->assertContains($mine->invoice->number, $cells);
        $this->assertNotContains($other->invoice->number, $cells);
        $this->assertContains(500000.0, array_map(fn ($c) => is_numeric($c) ? (float) $c : $c, $cells));
    }

    public function test_pdf_export_returns_pdf_and_exports_validate_period_and_permission(): void
    {
        $this->income($this->bdg, $this->coding, '2026-10-05 10:00');

        Sanctum::actingAs($this->makeStaff('trainer', 'all'));
        $this->get('/api/exports/income/excel?from=2026-10-01&to=2026-10-31')->assertForbidden();
        $this->get('/api/exports/income/pdf?from=2026-10-01&to=2026-10-31')->assertForbidden();

        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $pdf = $this->get('/api/exports/income/pdf?from=2026-10-01&to=2026-10-31')->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->getJson('/api/exports/income/excel')->assertStatus(422);
        $this->getJson('/api/exports/income/pdf')->assertStatus(422);
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
