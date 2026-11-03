<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 (KEU-03): kuitansi PDF terbit untuk invoice Lunas. Hak akses: manage invoices + scope cabang.
 */
class InvoiceReceiptTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgram();
    }

    private function invoice(string $status = 'paid', ?Branch $branch = null): Invoice
    {
        $registration = $this->makeRegistration(
            ['status' => 'terverifikasi', 'branch_id' => ($branch ?? $this->bdg)->id],
            $this->program
        );
        $invoice = $this->makeInvoice($registration, [
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
        if ($status === 'paid') {
            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
                'method' => 'transfer',
                'paid_date' => now()->toDateString(),
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        }

        return $invoice;
    }

    public function test_receipt_requires_login_and_manage_invoices_permission(): void
    {
        $invoice = $this->invoice();

        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertUnauthorized();

        Sanctum::actingAs($this->makeStaff('trainer', 'assigned', [$this->bdg->id]));
        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertForbidden();
    }

    public function test_paid_invoice_downloads_a_pdf_named_after_the_invoice_number(): void
    {
        $invoice = $this->invoice();
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));

        $res = $this->get("/api/invoices/{$invoice->id}/receipt")->assertOk();

        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'kuitansi-'.str_replace('/', '-', $invoice->number).'.pdf',
            $res->headers->get('Content-Disposition')
        );
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_unpaid_or_void_invoice_has_no_receipt(): void
    {
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));

        foreach (['unpaid', 'void'] as $status) {
            $invoice = $this->invoice($status);
            $this->getJson("/api/invoices/{$invoice->id}/receipt")
                ->assertStatus(422)
                ->assertJsonValidationErrors('status');
        }
    }

    public function test_receipt_of_another_branch_returns_404(): void
    {
        $jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $invoice = $this->invoice('paid', $jkt);
        Sanctum::actingAs($this->makeStaff('finance', 'assigned', [$this->bdg->id]));

        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertNotFound();
    }
}
