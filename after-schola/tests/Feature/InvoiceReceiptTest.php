<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * KEU-03: kuitansi PDF hanya untuk invoice lunas, dibatasi cabang.
 */
class InvoiceReceiptTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_paid_invoice_returns_pdf(): void
    {
        $bdg = $this->newBranch('BDG');
        $invoice = $this->makeInvoice($this->makeParentRegistration($bdg), ['status' => 'paid', 'paid_at' => now()]);
        $this->makePayment($invoice, ['status' => 'confirmed', 'confirmed_at' => now()]);
        Sanctum::actingAs($this->makeBranchStaff('finance', $bdg));

        $res = $this->get("/api/invoices/{$invoice->id}/receipt")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_unpaid_invoice_is_rejected(): void
    {
        $bdg = $this->newBranch('BDG');
        $invoice = $this->makeInvoice($this->makeParentRegistration($bdg));
        Sanctum::actingAs($this->makeBranchStaff('finance', $bdg));

        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertStatus(422);
    }

    public function test_other_branch_gets_404_and_trainer_gets_403(): void
    {
        $bdg = $this->newBranch('BDG');
        $jkt = $this->newBranch('JKT');
        $invoice = $this->makeInvoice($this->makeParentRegistration($jkt), ['status' => 'paid', 'paid_at' => now()]);

        Sanctum::actingAs($this->makeBranchStaff('finance', $bdg));
        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertNotFound();

        Sanctum::actingAs($this->makeUser('trainer'));
        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertForbidden();
    }
}
