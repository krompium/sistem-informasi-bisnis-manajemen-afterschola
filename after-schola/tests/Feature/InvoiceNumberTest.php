<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * BR-06: nomor invoice INV/{kode cabang}/{YYYYMM}/{urut}; urutan per cabang per bulan.
 */
class InvoiceNumberTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Sanctum::actingAs($this->makeUser('management'));
    }

    private function approve($registration): void
    {
        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();
    }

    public function test_number_contains_branch_code_and_counts_per_branch(): void
    {
        $bdg = $this->newBranch('BDG');
        $jkt = $this->newBranch('JKT');
        $ym = now()->format('Ym');

        foreach ([$bdg, $bdg, $jkt] as $branch) {
            $this->approve($this->makeParentRegistration($branch, ['status' => 'menunggu_verifikasi']));
        }

        $this->assertSame(
            ["INV/BDG/{$ym}/0001", "INV/BDG/{$ym}/0002", "INV/JKT/{$ym}/0001"],
            Invoice::orderBy('id')->pluck('number')->all(),
        );
    }

    public function test_registration_without_branch_keeps_legacy_format(): void
    {
        $this->approve($this->makeRegistration(['status' => 'menunggu_verifikasi'], $this->makeProgram()));

        $this->assertSame('INV/'.now()->format('Ym').'/0001', Invoice::first()->number);
    }
}
