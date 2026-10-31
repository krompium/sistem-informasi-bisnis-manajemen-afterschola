<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 lanjutan (5a): isolasi cabang untuk invoice, payment, dan umur piutang (BR-19, AC-08),
 * serta kode cabang di nomor invoice (BR-06).
 */
class FinanceBranchIsolationTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    private User $financeBdg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgram();
        $this->financeBdg = $this->makeStaff('finance', 'assigned', [$this->bdg->id]);
    }

    private function invoiceIn(?Branch $branch, array $overrides = []): Invoice
    {
        $registration = $this->makeRegistration(
            ['status' => 'terverifikasi', 'branch_id' => $branch?->id],
            $this->program
        );

        return $this->makeInvoice($registration, $overrides);
    }

    private function pendingPayment(Invoice $invoice): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'method' => 'tunai',
            'paid_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
    }

    public function test_invoice_list_only_contains_own_branches_and_all_scope_sees_everything(): void
    {
        $bdg = $this->invoiceIn($this->bdg);
        $jkt = $this->invoiceIn($this->jkt);
        $legacy = $this->invoiceIn(null);

        Sanctum::actingAs($this->financeBdg);
        $ids = $this->getJson('/api/invoices')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$bdg->id], $ids);

        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $ids = $this->getJson('/api/invoices')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$bdg->id, $jkt->id, $legacy->id], $ids);
    }

    public function test_invoice_list_can_be_filtered_by_branch(): void
    {
        $bdg = $this->invoiceIn($this->bdg);
        $jkt = $this->invoiceIn($this->jkt);

        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $ids = $this->getJson("/api/invoices?branch_id={$this->jkt->id}")->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$jkt->id], $ids);

        // Filter tidak boleh membuka cabang di luar scope.
        Sanctum::actingAs($this->financeBdg);
        $this->getJson("/api/invoices?branch_id={$this->jkt->id}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_invoice_list_exposes_only_visible_active_branches_as_filter_options(): void
    {
        $this->makeBranch('Cabang Tutup', 'TTP', ['is_active' => false]);

        Sanctum::actingAs($this->financeBdg);
        $this->getJson('/api/invoices')->assertOk()
            ->assertJsonCount(1, 'options.branches')
            ->assertJsonPath('options.branches.0.id', $this->bdg->id);

        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $this->getJson('/api/invoices')->assertOk()->assertJsonCount(2, 'options.branches');
    }

    public function test_invoice_in_other_branch_returns_404_on_show_void_and_record_payment(): void
    {
        $other = $this->invoiceIn($this->jkt);
        Sanctum::actingAs($this->financeBdg);

        $this->getJson("/api/invoices/{$other->id}")->assertNotFound();
        $this->postJson("/api/invoices/{$other->id}/void", ['reason' => 'salah input'])->assertNotFound();
        $this->postJson("/api/invoices/{$other->id}/payments", [
            'method' => 'tunai',
            'paid_date' => now()->toDateString(),
        ])->assertNotFound();

        $this->assertSame('unpaid', $other->fresh()->status);
        $this->assertSame(0, $other->payments()->count());
    }

    public function test_payment_in_other_branch_returns_404_on_confirm_and_reject(): void
    {
        $payment = $this->pendingPayment($this->invoiceIn($this->jkt));
        Sanctum::actingAs($this->financeBdg);

        $this->postJson("/api/payments/{$payment->id}/confirm")->assertNotFound();
        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'buram'])->assertNotFound();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_own_branch_invoice_and_payment_still_work(): void
    {
        $invoice = $this->invoiceIn($this->bdg);
        $payment = $this->pendingPayment($invoice);
        Sanctum::actingAs($this->financeBdg);

        $this->getJson("/api/invoices/{$invoice->id}")->assertOk();
        $this->postJson("/api/payments/{$payment->id}/confirm")->assertOk();
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_aging_report_is_scoped_to_user_branches(): void
    {
        $this->invoiceIn($this->bdg);
        $this->invoiceIn($this->jkt);

        Sanctum::actingAs($this->financeBdg);
        $this->getJson('/api/finance/aging')->assertOk()
            ->assertJsonPath('data.total.count', 1)
            ->assertJsonCount(1, 'data.invoices');

        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $this->getJson('/api/finance/aging')->assertOk()->assertJsonPath('data.total.count', 2);
    }

    public function test_invoice_number_contains_branch_code_and_sequence_is_per_branch(): void
    {
        Sanctum::actingAs($this->makeAdmin('all'));
        $ym = now()->format('Ym');
        $approve = fn (?Branch $branch) => $this->postJson(
            '/api/registrations/'.$this->makeRegistration(['branch_id' => $branch?->id], $this->program)->id.'/approve'
        )->assertOk();

        $approve($this->bdg);
        $approve($this->bdg);
        $approve($this->jkt);

        $numbers = Invoice::orderBy('id')->pluck('number')->all();
        $this->assertSame(["INV/BDG/{$ym}/0001", "INV/BDG/{$ym}/0002", "INV/JKT/{$ym}/0001"], $numbers);
    }

    public function test_registration_without_branch_keeps_legacy_invoice_number_format(): void
    {
        Sanctum::actingAs($this->makeAdmin('all'));
        $registration = $this->makeRegistration(['branch_id' => null], $this->program);

        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();

        $this->assertSame('INV/'.now()->format('Ym').'/0001', Invoice::firstOrFail()->number);
    }
}
