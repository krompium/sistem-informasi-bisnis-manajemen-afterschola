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
 * Modul 5 (BR-19, PRD bagian 9): invoice dan payment dibatasi scope cabang. Di luar cabang = 404.
 */
class InvoiceBranchIsolationTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private Branch $bdg;

    private Branch $jkt;

    private Program $program;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $this->program = $this->makeProgram();
        $this->finance = $this->makeStaff('finance', 'assigned', [$this->bdg->id]);
        Sanctum::actingAs($this->finance);
    }

    private function invoiceIn(Branch $branch, array $overrides = []): Invoice
    {
        $registration = $this->makeRegistration(['status' => 'terverifikasi', 'branch_id' => $branch->id], $this->program);

        return $this->makeInvoice($registration, $overrides);
    }

    private function pendingPaymentOn(Invoice $invoice): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'method' => 'tunai',
            'paid_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
    }

    public function test_invoice_list_only_contains_invoices_of_assigned_branches(): void
    {
        $mine = $this->invoiceIn($this->bdg);
        $this->invoiceIn($this->jkt);

        $res = $this->getJson('/api/invoices')->assertOk();

        $this->assertSame([$mine->id], collect($res->json('data'))->pluck('id')->all());
    }

    public function test_scope_all_sees_invoices_of_every_branch(): void
    {
        $this->invoiceIn($this->bdg);
        $this->invoiceIn($this->jkt);
        Sanctum::actingAs($this->makeStaff('finance', 'all'));

        $this->assertCount(2, $this->getJson('/api/invoices')->json('data'));
    }

    public function test_invoice_detail_of_another_branch_returns_404(): void
    {
        $mine = $this->invoiceIn($this->bdg);
        $other = $this->invoiceIn($this->jkt);

        $this->getJson("/api/invoices/{$mine->id}")->assertOk();
        $this->getJson("/api/invoices/{$other->id}")->assertNotFound();
    }

    public function test_void_invoice_of_another_branch_returns_404_and_changes_nothing(): void
    {
        $other = $this->invoiceIn($this->jkt);

        $this->postJson("/api/invoices/{$other->id}/void", ['reason' => 'Salah cabang'])->assertNotFound();

        $this->assertSame('unpaid', $other->fresh()->status);
    }

    public function test_recording_payment_on_invoice_of_another_branch_returns_404(): void
    {
        $other = $this->invoiceIn($this->jkt);

        $this->postJson("/api/invoices/{$other->id}/payments", [
            'method' => 'tunai',
            'paid_date' => now()->toDateString(),
        ])->assertNotFound();

        $this->assertSame(0, Payment::count());
    }

    public function test_confirming_or_rejecting_payment_of_another_branch_returns_404(): void
    {
        $payment = $this->pendingPaymentOn($this->invoiceIn($this->jkt));

        $this->postJson("/api/payments/{$payment->id}/confirm")->assertNotFound();
        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'Tidak cocok'])->assertNotFound();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_confirming_payment_in_own_branch_still_works(): void
    {
        $invoice = $this->invoiceIn($this->bdg);
        $payment = $this->pendingPaymentOn($invoice);

        $this->postJson("/api/payments/{$payment->id}/confirm")->assertOk();

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_aging_report_only_counts_invoices_of_assigned_branches(): void
    {
        $this->invoiceIn($this->bdg, ['due_date' => now()->subDays(5)->toDateString()]);
        $this->invoiceIn($this->jkt, ['due_date' => now()->subDays(5)->toDateString()]);

        $this->getJson('/api/finance/aging')->assertOk()
            ->assertJsonPath('data.total.count', 1)
            ->assertJsonPath('data.buckets.d1_30.count', 1);
    }
}
