<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 (KEU-07, KPI Income di PRD 6.3): pemasukan = payments confirmed dengan confirmed_at dalam periode
 * (hari penuh zona Asia/Jakarta), per cabang dan program, plus export Excel dan PDF. Scope cabang (BR-19).
 */
class IncomeReportTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private const PERIOD = ['from' => '2026-11-01', 'to' => '2026-11-30'];

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
        $this->coding = $this->makeProgram();
        $this->robotik = Program::create(['name' => 'Robotik', 'jenjang' => 'SMP', 'tipe' => 'offline', 'price' => 700000, 'is_active' => true]);
        Sanctum::actingAs($this->makeStaff('finance', 'all'));
    }

    private function payment(Branch $branch, Program $program, float $amount, string $status = 'confirmed', string $confirmedAt = '2026-11-10 03:00:00'): Payment
    {
        $registration = $this->makeRegistration(['status' => 'terverifikasi', 'branch_id' => $branch->id], $program);
        $invoice = $this->makeInvoice($registration, ['amount' => $amount, 'status' => $status === 'confirmed' ? 'paid' : 'unpaid']);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'method' => 'transfer',
            'paid_date' => '2026-11-10',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? Carbon::parse($confirmedAt, 'UTC') : null,
        ]);
    }

    private function income(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/finance/income?'.http_build_query(self::PERIOD + $query));
    }

    private function xlsxRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'income').'.xlsx';
        file_put_contents($path, $content);
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        return $rows;
    }

    public function test_requires_view_finance_reports_permission_on_every_endpoint(): void
    {
        $urls = ['/api/finance/income', '/api/exports/income/excel', '/api/exports/income/pdf'];

        Sanctum::actingAs($this->makeStaff('trainer', 'assigned', [$this->bdg->id]));
        foreach ($urls as $url) {
            $this->getJson($url)->assertForbidden();
        }

        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        foreach ($urls as $url) {
            $this->get($url, ['Accept' => 'application/json'])->assertOk();
        }
    }

    public function test_sums_only_confirmed_payments_inside_the_period(): void
    {
        $this->payment($this->bdg, $this->coding, 500000);
        $this->payment($this->bdg, $this->coding, 300000, 'confirmed', '2026-11-30 16:59:00'); // 30 Nov 23:59 WIB
        $this->payment($this->bdg, $this->coding, 100000, 'pending');
        $this->payment($this->bdg, $this->coding, 100000, 'rejected');
        $this->payment($this->bdg, $this->coding, 900000, 'confirmed', '2026-10-31 16:59:00'); // 31 Okt 23:59 WIB
        $this->payment($this->bdg, $this->coding, 900000, 'confirmed', '2026-11-30 17:00:00'); // 1 Des 00:00 WIB

        $this->income()->assertOk()
            ->assertJsonPath('data.total', 800000)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.period.from', '2026-11-01')
            ->assertJsonPath('data.period.to', '2026-11-30');
    }

    public function test_period_starts_at_midnight_jakarta_time(): void
    {
        $this->payment($this->bdg, $this->coding, 250000, 'confirmed', '2026-10-31 17:30:00'); // 1 Nov 00:30 WIB

        $this->income()->assertOk()->assertJsonPath('data.total', 250000);
    }

    public function test_defaults_to_the_current_month(): void
    {
        $this->payment($this->bdg, $this->coding, 400000, 'confirmed', now()->toDateTimeString());
        $this->payment($this->bdg, $this->coding, 600000, 'confirmed', now()->subDays(40)->toDateTimeString());

        $this->getJson('/api/finance/income')->assertOk()
            ->assertJsonPath('data.total', 400000)
            ->assertJsonPath('data.period.from', now()->timezone(config('app.display_timezone'))->startOfMonth()->toDateString());
    }

    public function test_groups_totals_by_branch_and_program(): void
    {
        $this->payment($this->bdg, $this->coding, 500000);
        $this->payment($this->bdg, $this->robotik, 700000);
        $this->payment($this->jkt, $this->coding, 500000);

        $res = $this->income()->assertOk();

        $byBranch = collect($res->json('data.by_branch'))->keyBy('branch_name');
        $this->assertSame(1200000.0, (float) $byBranch['Cabang Bandung']['total']);
        $this->assertSame(2, $byBranch['Cabang Bandung']['count']);
        $this->assertSame(500000.0, (float) $byBranch['Cabang Jakarta']['total']);

        $byProgram = collect($res->json('data.by_program'))->keyBy('program_name');
        $this->assertSame(1000000.0, (float) $byProgram['Coding Kids']['total']);
        $this->assertSame(700000.0, (float) $byProgram['Robotik']['total']);
    }

    public function test_rows_describe_each_payment(): void
    {
        $this->payment($this->bdg, $this->robotik, 700000);

        $this->income()->assertOk()
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student_name', 'Budi Santoso')
            ->assertJsonPath('data.rows.0.program_name', 'Robotik')
            ->assertJsonPath('data.rows.0.branch_name', 'Cabang Bandung')
            ->assertJsonPath('data.rows.0.method', 'transfer')
            ->assertJsonPath('data.rows.0.amount', 700000);
    }

    public function test_filters_by_branch_and_program(): void
    {
        $this->payment($this->bdg, $this->coding, 500000);
        $this->payment($this->bdg, $this->robotik, 700000);
        $this->payment($this->jkt, $this->coding, 300000);

        $this->income(['branch_id' => $this->bdg->id])->assertJsonPath('data.total', 1200000);
        $this->income(['program_id' => $this->coding->id])->assertJsonPath('data.total', 800000);
        $this->income(['branch_id' => $this->bdg->id, 'program_id' => $this->coding->id])->assertJsonPath('data.total', 500000);
    }

    public function test_assigned_staff_only_sees_income_of_their_branches(): void
    {
        $this->payment($this->bdg, $this->coding, 500000);
        $this->payment($this->jkt, $this->coding, 900000);
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));

        $this->income()->assertOk()
            ->assertJsonPath('data.total', 500000)
            ->assertJsonCount(1, 'data.by_branch');
        // Meminta cabang lain tidak membocorkan apa pun.
        $this->income(['branch_id' => $this->jkt->id])->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.count', 0);
    }

    public function test_filter_options_list_only_accessible_branches_and_all_programs(): void
    {
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));

        $res = $this->income()->assertOk();

        $this->assertSame(['Cabang Bandung'], collect($res->json('data.options.branches'))->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Coding Kids', 'Robotik'], collect($res->json('data.options.programs'))->pluck('name')->all());
    }

    public function test_period_must_be_valid(): void
    {
        $this->getJson('/api/finance/income?from=2026-11-30&to=2026-11-01')->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson('/api/finance/income?from=bukan-tanggal')->assertStatus(422)->assertJsonValidationErrors('from');
    }

    public function test_excel_export_lists_the_payments_and_a_total_row(): void
    {
        $this->payment($this->bdg, $this->coding, 500000);
        $this->payment($this->jkt, $this->robotik, 700000);

        $res = $this->get('/api/exports/income/excel?'.http_build_query(self::PERIOD))->assertOk();

        $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('pemasukan-20261101-20261130.xlsx', $res->headers->get('Content-Disposition'));
        $rows = $this->xlsxRows($res->streamedContent());
        $this->assertCount(4, $rows); // header + 2 pembayaran + total
        $this->assertContains('Nomor Invoice', $rows[0]);
        $this->assertSame('TOTAL', $rows[3][0]);
        $this->assertSame(1200000.0, (float) end($rows[3]));
    }

    public function test_excel_export_follows_branch_scope_and_filters(): void
    {
        $this->payment($this->bdg, $this->coding, 500000);
        $this->payment($this->jkt, $this->coding, 900000);
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));

        $rows = $this->xlsxRows($this->get('/api/exports/income/excel?'.http_build_query(self::PERIOD))->streamedContent());

        $this->assertCount(3, $rows); // header + 1 pembayaran + total
        $this->assertSame(500000.0, (float) end($rows[2]));
    }

    public function test_pdf_export_downloads_a_pdf(): void
    {
        $this->payment($this->bdg, $this->coding, 500000);

        $res = $this->get('/api/exports/income/pdf?'.http_build_query(self::PERIOD))->assertOk();

        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('pemasukan-20261101-20261130.pdf', $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}
