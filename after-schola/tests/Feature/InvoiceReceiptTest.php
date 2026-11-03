<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 lanjutan (5b, KEU-03): kuitansi PDF setelah invoice lunas.
 */
class InvoiceReceiptTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private Branch $bdg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
    }

    private function invoice(string $status, ?Branch $branch = null): Invoice
    {
        static $seq = 0;
        $seq++;
        $registration = $this->makeRegistration(
            ['status' => 'terverifikasi', 'branch_id' => ($branch ?? $this->bdg)->id],
            $this->makeProgram()
        );
        $invoice = $this->makeInvoice($registration, [
            'number' => sprintf('INV/BDG/%s/%04d', now()->format('Ym'), $seq),
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
        if ($status === 'paid') {
            Payment::create([
                'invoice_id' => $invoice->id, 'amount' => $invoice->amount, 'method' => 'transfer',
                'paid_date' => now()->toDateString(), 'status' => 'confirmed', 'confirmed_at' => now(),
            ]);
        }

        return $invoice;
    }

    public function test_paid_invoice_returns_receipt_pdf_with_safe_filename(): void
    {
        $invoice = $this->invoice('paid');
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));

        $response = $this->get("/api/invoices/{$invoice->id}/receipt")->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'kuitansi-'.str_replace('/', '-', $invoice->number).'.pdf',
            $response->headers->get('content-disposition')
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_receipt_is_rejected_for_unpaid_and_void_invoices(): void
    {
        Sanctum::actingAs($this->makeStaff('finance', 'all'));

        foreach (['unpaid', 'void'] as $status) {
            $invoice = $this->invoice($status);
            $this->getJson("/api/invoices/{$invoice->id}/receipt")
                ->assertStatus(422)
                ->assertJsonValidationErrors('invoice');
        }
    }

    public function test_receipt_of_other_branch_returns_404_and_non_finance_is_forbidden(): void
    {
        $jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $invoice = $this->invoice('paid', $jkt);

        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertUnauthorized();

        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));
        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertNotFound();

        Sanctum::actingAs($this->makeStaff('trainer', 'assigned', [$this->bdg->id]));
        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertForbidden();
    }
}
