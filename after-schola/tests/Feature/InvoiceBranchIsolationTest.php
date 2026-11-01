<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * BR-19 / AC-08: invoice, pembayaran, dan laporan piutang dibatasi cabang. Di luar scope = 404.
 */
class InvoiceBranchIsolationTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function twoBranches(): array
    {
        $bdg = $this->newBranch('BDG');
        $jkt = $this->newBranch('JKT');
        $mine = $this->makeInvoice($this->makeParentRegistration($bdg));
        $other = $this->makeInvoice($this->makeParentRegistration($jkt));

        return [$bdg, $mine, $other];
    }

    public function test_listing_and_detail_only_show_own_branch(): void
    {
        [$bdg, $mine, $other] = $this->twoBranches();
        Sanctum::actingAs($this->makeBranchStaff('finance', $bdg));

        $this->getJson('/api/invoices')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/invoices/{$mine->id}")->assertOk();
        $this->getJson("/api/invoices/{$other->id}")->assertNotFound();
    }

    public function test_cannot_record_void_or_decide_payments_of_other_branch(): void
    {
        [$bdg, , $other] = $this->twoBranches();
        $pending = $this->makePayment($other);
        Sanctum::actingAs($this->makeBranchStaff('finance', $bdg));

        $this->postJson("/api/invoices/{$other->id}/payments", ['method' => 'tunai', 'paid_date' => now()->toDateString()])->assertNotFound();
        $this->postJson("/api/invoices/{$other->id}/void", ['reason' => 'salah'])->assertNotFound();
        $this->postJson("/api/payments/{$pending->id}/confirm")->assertNotFound();
        $this->postJson("/api/payments/{$pending->id}/reject", ['reason' => 'salah'])->assertNotFound();

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('unpaid', $other->fresh()->status);
    }

    public function test_can_confirm_payment_of_own_branch(): void
    {
        [$bdg, $mine] = $this->twoBranches();
        $pending = $this->makePayment($mine);
        Sanctum::actingAs($this->makeBranchStaff('finance', $bdg));

        $this->postJson("/api/payments/{$pending->id}/confirm")->assertOk();
        $this->assertSame('paid', $mine->fresh()->status);
    }

    public function test_aging_report_only_counts_own_branch(): void
    {
        [$bdg] = $this->twoBranches();
        Sanctum::actingAs($this->makeBranchStaff('finance', $bdg));

        $this->getJson('/api/finance/aging')->assertOk()->assertJsonPath('data.total.count', 1);
    }

    public function test_all_scope_sees_every_branch(): void
    {
        $this->twoBranches();
        Sanctum::actingAs($this->makeUser('finance')); // fixture: scope all

        $this->getJson('/api/invoices')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/finance/aging')->assertOk()->assertJsonPath('data.total.count', 2);
    }
}
