<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-08/09): bayar invoice dengan upload bukti, lihat status, unduh kuitansi. Hanya invoice anak sendiri.
 */
class ParentPaymentTest extends TestCase
{
    use MakesParentFixtures, RefreshDatabase;

    private $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');
        $this->branch = $this->newBranch('BDG');
    }

    /** PNG 1x1 valid; $padTo menambah byte agar ukuran file bisa diatur tanpa ekstensi GD. */
    private function proof(int $padTo = 0, string $name = 'bukti.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $padTo > strlen($png) ? str_pad($png, $padTo, "\0") : $png);
    }

    private function pay(Invoice $invoice, array $data = [])
    {
        return $this->post("/api/parent/invoices/{$invoice->id}/payments", array_merge([
            'paid_date' => now()->toDateString(), 'proof' => $this->proof(),
        ], $data), ['Accept' => 'application/json']);
    }

    private function invoiceOf($child): Invoice
    {
        return Invoice::where('registration_id', $child->trialEvaluation->decision->registration->id)->firstOrFail();
    }

    public function test_parent_uploads_proof_and_payment_waits_for_confirmation(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'menunggu_bayar');
        Sanctum::actingAs($parent);

        $res = $this->pay($this->invoiceOf($child), ['method' => 'tunai'])->assertCreated()->assertJsonPath('data.status', 'pending');

        $payment = Payment::firstOrFail();
        $this->assertSame('transfer', $payment->method); // ortu hanya transfer; tunai dicatat staf
        $this->assertSame($parent->id, $payment->recorded_by);
        $this->assertMatchesRegularExpression('#^payment-proofs/[0-9a-f-]{36}\.png$#', $payment->proof_path);
        Storage::disk('public')->assertExists($payment->proof_path);
        $this->assertNotEmpty($res->json('data.proof_url'));

        $this->getJson("/api/parent/students/{$child->id}")->assertJsonPath('data.stage', 'menunggu_konfirmasi')
            ->assertJsonPath('data.invoice.payments.0.status', 'pending');
    }

    public function test_upload_rules_are_enforced(): void
    {
        $parent = $this->makeUser('parent');
        $invoice = $this->invoiceOf($this->makeChild($parent, $this->branch, 'menunggu_bayar'));
        Sanctum::actingAs($parent);

        $this->postJson("/api/parent/invoices/{$invoice->id}/payments", ['paid_date' => now()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('proof');
        $this->pay($invoice, ['proof' => UploadedFile::fake()->createWithContent('bukti.pdf', '%PDF-1.4')])->assertStatus(422)->assertJsonValidationErrors('proof');
        $this->pay($invoice, ['proof' => $this->proof(6 * 1024 * 1024)])->assertStatus(422)->assertJsonValidationErrors('proof'); // > 5 MB
        $this->pay($invoice, ['paid_date' => now()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('paid_date');
        $this->assertSame(0, Payment::count());
    }

    public function test_cannot_pay_twice_but_can_reupload_after_rejection(): void
    {
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'menunggu_konfirmasi');
        $invoice = $this->invoiceOf($child);
        Sanctum::actingAs($parent);

        $this->pay($invoice)->assertStatus(422); // sudah ada bukti yang menunggu

        Payment::firstOrFail()->update(['status' => 'rejected', 'reject_reason' => 'Bukti buram']);
        $this->getJson("/api/parent/students/{$child->id}")->assertJsonPath('data.stage', 'menunggu_bayar')
            ->assertJsonPath('data.invoice.payments.0.reject_reason', 'Bukti buram');
        $this->pay($invoice)->assertCreated();
    }

    public function test_detail_includes_payment_instructions_while_unpaid(): void
    {
        \App\Models\PaymentSetting::current()->update(['bank_account' => 'BCA 123456 a.n. After Schola', 'payment_instructions' => 'Transfer lalu unggah bukti.']);
        $parent = $this->makeUser('parent');
        $child = $this->makeChild($parent, $this->branch, 'menunggu_bayar');
        Sanctum::actingAs($parent);

        $this->getJson("/api/parent/students/{$child->id}")->assertOk()
            ->assertJsonPath('data.payment_info.bank_account', 'BCA 123456 a.n. After Schola')
            ->assertJsonPath('data.payment_info.instructions', 'Transfer lalu unggah bukti.');
    }

    public function test_receipt_only_for_own_paid_invoice(): void
    {
        $parent = $this->makeUser('parent');
        $paid = $this->invoiceOf($this->makeChild($parent, $this->branch, 'lunas'));
        $unpaid = $this->invoiceOf($this->makeChild($parent, $this->branch, 'menunggu_bayar', 'Adik'));
        Sanctum::actingAs($parent);

        $res = $this->get("/api/parent/invoices/{$paid->id}/receipt")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->getJson("/api/parent/invoices/{$unpaid->id}/receipt")->assertStatus(422);
    }

    public function test_another_parent_gets_404_and_staff_cannot_use_parent_endpoints(): void
    {
        $paid = $this->invoiceOf($this->makeChild($this->makeUser('parent'), $this->branch, 'lunas'));
        $unpaid = $this->invoiceOf($this->makeChild($this->makeUser('parent'), $this->branch, 'menunggu_bayar', 'Anak Lain'));

        Sanctum::actingAs($this->makeUser('parent'));
        $this->pay($unpaid)->assertNotFound();
        $this->getJson("/api/parent/invoices/{$paid->id}/receipt")->assertNotFound();
        $this->assertSame(0, Payment::where('status', 'pending')->count());

        Sanctum::actingAs($this->makeUser('finance'));
        $this->pay($unpaid)->assertForbidden();
    }

    public function test_parent_cannot_use_staff_finance_endpoints(): void
    {
        $parent = $this->makeUser('parent');
        $invoice = $this->invoiceOf($this->makeChild($parent, $this->branch, 'menunggu_konfirmasi'));
        Sanctum::actingAs($parent);

        $this->getJson('/api/invoices')->assertForbidden();
        $this->postJson('/api/payments/'.Payment::firstOrFail()->id.'/confirm')->assertForbidden();
        $this->getJson("/api/invoices/{$invoice->id}/receipt")->assertForbidden();
    }
}
