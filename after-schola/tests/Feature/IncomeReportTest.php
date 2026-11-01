<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * KEU-07 / KPI income: jumlah payment confirmed dengan confirmed_at dalam periode, per cabang dan program.
 */
class IncomeReportTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    private $bdg;

    private $jkt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->newBranch('BDG');
        $this->jkt = $this->newBranch('JKT');
        $coding = Program::create(['name' => 'Coding', 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 500000, 'is_active' => true]);
        $robot = Program::create(['name' => 'Robotik', 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => 300000, 'is_active' => true]);

        $this->paid($this->bdg, $coding, 500000, '2026-10-10');
        $this->paid($this->bdg, $coding, 500000, '2026-09-10'); // di luar periode
        $this->paid($this->jkt, $robot, 300000, '2026-10-20');
        $pending = $this->makeInvoice($this->makeParentRegistration($this->bdg, ['program_id' => $coding->id]));
        $this->makePayment($pending, ['status' => 'pending']);   // belum dikonfirmasi: tidak dihitung
    }

    private function paid($branch, Program $program, int $amount, string $confirmedAt): void
    {
        $invoice = $this->makeInvoice($this->makeParentRegistration($branch, ['program_id' => $program->id]), ['amount' => $amount, 'status' => 'paid', 'paid_at' => $confirmedAt]);
        $this->makePayment($invoice, ['amount' => $amount, 'status' => 'confirmed', 'confirmed_at' => $confirmedAt.' 10:00:00']);
    }

    private const RANGE = 'from=2026-10-01&to=2026-10-31';

    public function test_period_is_required_and_permission_is_checked(): void
    {
        Sanctum::actingAs($this->makeUser('finance'));
        $this->getJson('/api/finance/income')->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);

        Sanctum::actingAs($this->makeUser('trainer'));
        $this->getJson('/api/finance/income?'.self::RANGE)->assertForbidden();
    }

    public function test_sums_only_confirmed_payments_inside_period_per_branch_and_program(): void
    {
        Sanctum::actingAs($this->makeUser('finance'));

        $res = $this->getJson('/api/finance/income?'.self::RANGE)->assertOk();
        $res->assertJsonPath('data.total', 800000)->assertJsonPath('data.count', 2);
        $this->assertEqualsCanonicalizing([500000, 300000], array_column($res->json('data.by_branch'), 'total'));
        $this->assertEqualsCanonicalizing(['Coding', 'Robotik'], array_column($res->json('data.by_program'), 'name'));

        $this->getJson('/api/finance/income?'.self::RANGE.'&branch_id='.$this->jkt->id)
            ->assertOk()->assertJsonPath('data.total', 300000);
    }

    public function test_assigned_staff_only_sees_own_branch(): void
    {
        Sanctum::actingAs($this->makeBranchStaff('finance', $this->bdg));

        $this->getJson('/api/finance/income?'.self::RANGE)->assertOk()->assertJsonPath('data.total', 500000);
        $this->getJson('/api/finance/income?'.self::RANGE.'&branch_id='.$this->jkt->id)->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_excel_export_lists_confirmed_payments_with_total(): void
    {
        Sanctum::actingAs($this->makeUser('finance'));

        $res = $this->get('/api/exports/income/excel?'.self::RANGE)->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'inc').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        $this->assertCount(4, $rows); // header + 2 pembayaran + total
        $this->assertSame('TOTAL', $rows[3][5]);
        $this->assertEquals(800000, $rows[3][6]);
    }

    public function test_pdf_export_and_permission(): void
    {
        Sanctum::actingAs($this->makeUser('finance'));
        $res = $this->get('/api/exports/income/pdf?'.self::RANGE)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());

        Sanctum::actingAs($this->makeUser('trainer'));
        $this->getJson('/api/exports/income/excel?'.self::RANGE)->assertForbidden();
        $this->getJson('/api/exports/income/pdf?'.self::RANGE)->assertForbidden();
    }
}
