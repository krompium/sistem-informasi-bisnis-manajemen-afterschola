<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Umur piutang: invoice unpaid dikelompokkan menurut keterlambatan dari jatuh tempo.
 */
class AgingReportTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_requires_view_finance_reports_permission(): void
    {
        $this->getJson('/api/finance/aging')->assertStatus(401);

        Sanctum::actingAs($this->makeUser('trainer'));
        $this->getJson('/api/finance/aging')->assertStatus(403);

        Sanctum::actingAs($this->makeUser('finance'));
        $this->getJson('/api/finance/aging')->assertOk();
    }

    public function test_groups_unpaid_invoices_into_buckets_and_ignores_paid_and_void(): void
    {
        $days = ['belum_jatuh_tempo' => [3, 100000], 'd1_30' => [-5, 200000], 'd31_60' => [-45, 300000], 'd61_90' => [-75, 400000], 'd90_plus' => [-120, 500000]];
        foreach ($days as [$offset, $amount]) {
            $this->makeInvoice(null, ['due_date' => now()->addDays($offset)->toDateString(), 'amount' => $amount]);
        }
        $this->makeInvoice(null, ['due_date' => now()->subDays(10)->toDateString(), 'status' => 'paid', 'paid_at' => now()]);
        $this->makeInvoice(null, ['due_date' => now()->subDays(10)->toDateString(), 'status' => 'void']);
        Sanctum::actingAs($this->makeUser('finance'));

        $res = $this->getJson('/api/finance/aging')->assertOk();

        foreach ($days as $bucket => [, $amount]) {
            $this->assertSame(1, $res->json("data.buckets.{$bucket}.count"), $bucket);
            $this->assertEquals($amount, $res->json("data.buckets.{$bucket}.total"), $bucket);
        }
        $this->assertSame(5, $res->json('data.total.count'));
        $this->assertEquals(1500000, $res->json('data.total.total'));
        $this->assertCount(5, $res->json('data.invoices'));
    }
}
