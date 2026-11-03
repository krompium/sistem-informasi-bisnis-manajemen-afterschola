<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * SYS-05 / PRD 10: API integrasi read-only /api/integration/v1 untuk Tim Keuangan.
 * Token ber-ability finance:read; token ber-ability tidak boleh dipakai di API staf; data dibatasi cabang akun layanan.
 */
class IntegrationApiTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private $bdg;

    private $jkt;

    private Invoice $paid;

    private Invoice $unpaid;

    private Invoice $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->jkt = $this->makeBranch('Cabang Jakarta', 'JKT');
        $program = $this->makeProgram();
        $invoiceIn = fn ($branch, array $overrides = []) => $this->makeInvoice(
            $this->makeRegistration(['status' => 'terverifikasi', 'branch_id' => $branch->id], $program),
            $overrides,
        );
        $this->paid = $invoiceIn($this->bdg, ['status' => 'paid', 'paid_at' => now()]);
        $this->unpaid = $invoiceIn($this->bdg);
        $this->other = $invoiceIn($this->jkt);
        $this->makePayment($this->paid, ['status' => 'confirmed', 'confirmed_at' => now(), 'method' => 'transfer', 'proof_path' => 'payment-proofs/x.png']);
        $this->makePayment($this->unpaid, ['status' => 'pending']);
        $this->makePayment($this->other, ['status' => 'rejected', 'reject_reason' => 'Buram']);
    }

    private function makePayment(Invoice $invoice, array $overrides): Payment
    {
        return Payment::create(array_merge([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'method' => 'tunai',
            'paid_date' => now()->toDateString(),
            'status' => 'pending',
        ], $overrides));
    }

    /** Request dengan token sungguhan (bukan Sanctum::actingAs) supaya ability benar-benar dievaluasi. */
    private function withIntegrationToken($user, array $abilities = ['finance:read']): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('integration:Test', $abilities)->plainTextToken);
    }

    public function test_requires_a_token_with_finance_read_ability(): void
    {
        $this->getJson('/api/integration/v1/invoices')->assertUnauthorized();

        $this->withIntegrationToken($this->makeUser('finance'), ['attendance:read'])
            ->getJson('/api/integration/v1/invoices')->assertForbidden();
        $this->withIntegrationToken($this->makeUser('parent'))
            ->getJson('/api/integration/v1/invoices')->assertForbidden(); // bukan akun Keuangan
    }

    public function test_revoked_token_gets_401(): void
    {
        $service = $this->makeUser('finance');
        $token = $service->createToken('integration:Test', ['finance:read']);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/integration/v1/invoices')->assertOk();

        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/integration/v1/invoices')->assertUnauthorized();
    }

    public function test_lists_invoices_with_the_agreed_fields_only(): void
    {
        $res = $this->withIntegrationToken($this->makeUser('finance'))->getJson('/api/integration/v1/invoices')->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 3);

        $row = $res->json('data.0');
        $this->assertSame($this->paid->id, $row['id']);
        $this->assertEqualsCanonicalizing(
            ['id', 'number', 'status', 'amount', 'issued_at', 'due_date', 'paid_at', 'void_reason', 'student_name', 'parent_name', 'program', 'branch_code', 'updated_at'],
            array_keys($row),
        );
        $this->assertSame('BDG', $row['branch_code']);
        $this->assertSame('paid', $row['status']);
    }

    public function test_lists_payments_without_proof_files(): void
    {
        $res = $this->withIntegrationToken($this->makeUser('finance'))->getJson('/api/integration/v1/payments')->assertOk()->assertJsonCount(3, 'data');

        $this->assertEqualsCanonicalizing(
            ['id', 'invoice_id', 'invoice_number', 'amount', 'method', 'status', 'paid_date', 'confirmed_at', 'reject_reason', 'branch_code', 'updated_at'],
            array_keys($res->json('data.0')),
        );
        $this->assertStringNotContainsString('payment-proofs', $res->getContent());
    }

    public function test_data_is_limited_to_the_service_accounts_branches(): void
    {
        $service = $this->makeStaff('finance', 'assigned', [$this->bdg->id]);
        $this->withIntegrationToken($service);

        $this->getJson('/api/integration/v1/invoices')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/integration/v1/payments')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/integration/v1/invoices?branch_id='.$this->jkt->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filters_and_pagination(): void
    {
        $this->withIntegrationToken($this->makeUser('finance'));

        $this->getJson('/api/integration/v1/invoices?status=paid')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/integration/v1/invoices?branch_id='.$this->jkt->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->other->id);
        $this->getJson('/api/integration/v1/payments?status=confirmed')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/integration/v1/invoices?updated_since='.urlencode(now()->addDay()->toIso8601String()))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/integration/v1/invoices?updated_since='.urlencode(now()->subDay()->toIso8601String()))->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/integration/v1/invoices?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/integration/v1/invoices?status=bogus')->assertStatus(422);
        $this->getJson('/api/integration/v1/invoices?per_page=1000')->assertStatus(422);
    }

    public function test_scoped_token_cannot_use_the_staff_api(): void
    {
        $service = $this->makeUser('finance');
        $pending = Payment::where('status', 'pending')->firstOrFail();
        $this->withIntegrationToken($service);

        $this->getJson('/api/invoices')->assertForbidden();
        $this->getJson('/api/me')->assertForbidden();
        $this->postJson("/api/payments/{$pending->id}/confirm")->assertForbidden();
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_normal_login_token_still_works_on_the_staff_api(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->makeUser('finance')->createToken('web-spa')->plainTextToken);

        $this->getJson('/api/invoices')->assertOk();
    }
}
