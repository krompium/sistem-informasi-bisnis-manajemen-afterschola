<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * BR-06: nomor invoice INV/{kode cabang}/{YYYYMM}/{urut}, urutan per cabang per bulan.
 */
class InvoiceNumberTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Sanctum::actingAs($this->makeAdmin('all'));
    }

    private function approveRegistrationIn(?int $branchId): void
    {
        $registration = $this->makeRegistration(['branch_id' => $branchId], $this->makeProgram());

        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();
    }

    public function test_number_contains_branch_code_and_sequence_is_per_branch(): void
    {
        $bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $jkt = $this->makeBranch('Cabang Jakarta', 'JKT');

        $this->approveRegistrationIn($bdg->id);
        $this->approveRegistrationIn($bdg->id);
        $this->approveRegistrationIn($jkt->id);

        $ym = now()->format('Ym');
        $this->assertSame(
            ["INV/BDG/{$ym}/0001", "INV/BDG/{$ym}/0002", "INV/JKT/{$ym}/0001"],
            Invoice::orderBy('id')->pluck('number')->all(),
        );
    }

    public function test_registration_without_branch_keeps_legacy_format(): void
    {
        $this->approveRegistrationIn(null);

        $this->assertSame('INV/'.now()->format('Ym').'/0001', Invoice::firstOrFail()->number);
    }
}
