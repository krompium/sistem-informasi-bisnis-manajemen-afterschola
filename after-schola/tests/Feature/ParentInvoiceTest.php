<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesParentFixtures;
use Tests\TestCase;

/**
 * Portal Orang Tua (ORT-08, ORT-09; A-02, BR-07, AC-05, AC-07): lihat invoice dan instruksi bayar, unggah bukti transfer,
 * pantau status (menunggu, dikonfirmasi, ditolak beserta alasan), unduh kuitansi setelah lunas.
 */
class ParentInvoiceTest extends TestCase
{
    use MakesBranchFixtures, MakesParentFixtures, RefreshDatabase;

    private Branch $bdg;

    private Program $program;

    private User $parent;

    private Lead $child;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedRoles();
        $this->bdg = $this->makeBranch('Cabang Bandung', 'BDG');
        $this->program = $this->makeProgramWithLevel();
        $this->parent = $this->makeParent();
        $this->child = $this->makeChild($this->parent, $this->bdg, $this->program);
        $this->invoice = $this->invoiceFor($this->registrationFor($this->child, 'terverifikasi'));
        Sanctum::actingAs($this->parent);
    }

    /** PNG 1x1 valid; $padTo menambah byte agar ukuran file bisa diatur tanpa ekstensi GD. */
    private function proof(string $name = 'bukti.png', int $padTo = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $padTo > strlen($png) ? str_pad($png, $padTo, "\0") : $png);
    }

    private function pay(?Invoice $invoice = null, array $extra = [])
    {
        return $this->post('/api/parent/invoices/'.($invoice ?? $this->invoice)->id.'/payments', $extra + [
            'proof' => $this->proof(),
        ], ['Accept' => 'application/json']);
    }

    private function otherParentInvoice(): Invoice
    {
        $other = $this->makeChild($this->makeParent('Ibu Lain'), $this->bdg, $this->program, 'evaluated', 'Anak Lain');

        return $this->invoiceFor($this->registrationFor($other, 'terverifikasi'));
    }

    public function test_only_parents_can_use_the_invoice_endpoints(): void
    {
        foreach (['management', 'trainer', 'finance'] as $role) {
            Sanctum::actingAs($this->makeStaff($role, 'all'));
            $this->getJson('/api/parent/invoices')->assertForbidden();
            $this->postJson("/api/parent/invoices/{$this->invoice->id}/payments")->assertForbidden();
        }
    }

    public function test_lists_only_own_invoices_with_payment_instructions(): void
    {
        $this->otherParentInvoice();
        PaymentSetting::current()->update(['payment_instructions' => 'Transfer sebelum jatuh tempo', 'bank_account' => 'BCA 1234567890 a.n. After Schola']);

        $res = $this->getJson('/api/parent/invoices')->assertOk();

        $this->assertSame([$this->invoice->id], collect($res->json('data'))->pluck('id')->all());
        $res->assertJsonPath('data.0.number', $this->invoice->number)
            ->assertJsonPath('data.0.amount', 500000)
            ->assertJsonPath('data.0.student_name', 'Budi')
            ->assertJsonPath('data.0.status', 'unpaid')
            ->assertJsonPath('data.0.can_pay', true)
            ->assertJsonPath('data.0.receipt_available', false)
            ->assertJsonPath('meta.payment_info.instructions', 'Transfer sebelum jatuh tempo')
            ->assertJsonPath('meta.payment_info.bank_account', 'BCA 1234567890 a.n. After Schola');
    }

    public function test_uploading_proof_creates_a_pending_transfer_payment(): void
    {
        $res = $this->pay()->assertCreated();

        $payment = Payment::sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('transfer', $payment->method);
        $this->assertEquals($this->invoice->amount, $payment->amount);
        $this->assertSame($this->parent->id, $payment->recorded_by);
        $this->assertSame(today()->toDateString(), $payment->paid_date->toDateString());
        $this->assertMatchesRegularExpression('#^payment-proofs/[0-9a-f-]{36}\.png$#', $payment->proof_path); // nama uuid, path relatif
        Storage::disk('public')->assertExists($payment->proof_path);
        $res->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/parent/invoices')
            ->assertJsonPath('data.0.can_pay', false)
            ->assertJsonPath('data.0.payments.0.status', 'pending');
    }

    public function test_proof_is_required_and_limited_to_png_or_jpeg_images_up_to_5_mb(): void
    {
        $this->postJson("/api/parent/invoices/{$this->invoice->id}/payments", [])
            ->assertStatus(422)->assertJsonValidationErrors('proof');
        $this->pay(null, ['proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('proof');
        $this->pay(null, ['proof' => UploadedFile::fake()->createWithContent('bukti.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))])
            ->assertStatus(422)->assertJsonValidationErrors('proof');
        $this->pay(null, ['proof' => $this->proof('besar.png', 6000 * 1024)])
            ->assertStatus(422)->assertJsonValidationErrors('proof');

        $this->assertSame(0, Payment::count());
        $this->pay(null, ['proof' => $this->proof('bukti.jpeg')])->assertCreated();
    }

    public function test_payment_date_defaults_to_today_and_cannot_be_in_the_future(): void
    {
        $this->pay(null, ['paid_date' => now()->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('paid_date');

        $this->pay(null, ['paid_date' => now()->subDay()->toDateString()])->assertCreated();
        $this->assertSame(now()->subDay()->toDateString(), Payment::sole()->paid_date->toDateString());
    }

    public function test_cannot_pay_twice_while_waiting_or_when_the_invoice_is_not_payable(): void
    {
        $this->pay()->assertCreated();
        $this->pay()->assertStatus(422)->assertJsonValidationErrors('invoice');

        $paid = $this->invoiceFor($this->registrationFor($this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'Kakak'), 'terverifikasi'), 'paid');
        $void = $this->invoiceFor($this->registrationFor($this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'Adik'), 'terverifikasi'), 'void');
        $this->pay($paid)->assertStatus(422)->assertJsonValidationErrors('invoice');
        $this->pay($void)->assertStatus(422)->assertJsonValidationErrors('invoice');
        $this->assertSame(1, Payment::count());
    }

    public function test_rejected_payment_shows_the_reason_and_can_be_uploaded_again(): void
    {
        $this->pay()->assertCreated();
        $payment = Payment::sole();
        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'Bukti tidak terbaca'])->assertOk();

        Sanctum::actingAs($this->parent);
        $this->getJson('/api/parent/invoices')
            ->assertJsonPath('data.0.can_pay', true)
            ->assertJsonPath('data.0.payments.0.status', 'rejected')
            ->assertJsonPath('data.0.payments.0.reject_reason', 'Bukti tidak terbaca');

        $this->pay()->assertCreated();
        $this->assertSame(2, Payment::count());
    }

    public function test_confirmation_by_finance_makes_the_invoice_paid_with_a_receipt(): void
    {
        $this->pay()->assertCreated();
        Sanctum::actingAs($this->makeStaff('finance', 'all'));
        $this->postJson('/api/payments/'.Payment::sole()->id.'/confirm')->assertOk();

        Sanctum::actingAs($this->parent);
        $this->getJson('/api/parent/invoices')
            ->assertJsonPath('data.0.status', 'paid')
            ->assertJsonPath('data.0.receipt_available', true)
            ->assertJsonPath('data.0.can_pay', false);
        $this->getJson('/api/parent/children')->assertJsonPath('data.0.stage', 'siap_dijadwalkan');
    }

    public function test_parent_downloads_the_receipt_only_after_the_invoice_is_paid(): void
    {
        $this->getJson("/api/parent/invoices/{$this->invoice->id}/receipt")
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $paid = $this->invoiceFor($this->registrationFor($this->makeChild($this->parent, $this->bdg, $this->program, 'evaluated', 'Kakak'), 'terverifikasi'), 'paid');
        Payment::create(['invoice_id' => $paid->id, 'amount' => $paid->amount, 'method' => 'transfer', 'paid_date' => today(), 'status' => 'confirmed', 'confirmed_at' => now()]);

        $res = $this->get("/api/parent/invoices/{$paid->id}/receipt")->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_invoices_of_other_parents_return_404(): void
    {
        $other = $this->otherParentInvoice();
        $other->update(['status' => 'paid', 'paid_at' => now()]);

        $this->pay($other)->assertNotFound();
        $this->getJson("/api/parent/invoices/{$other->id}/receipt")->assertNotFound();
        $this->assertSame(0, Payment::count());
    }
}
