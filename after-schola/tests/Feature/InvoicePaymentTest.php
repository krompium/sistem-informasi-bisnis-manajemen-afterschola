<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 (bagian pembayaran): daftar invoice, catat pembayaran + bukti, konfirmasi/tolak, batalkan invoice.
 */
class InvoicePaymentTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');
    }

    /** PNG 1x1 valid; $padTo menambah byte agar ukuran file bisa diatur tanpa ekstensi GD. */
    private function proof(string $name = 'bukti.png', int $padTo = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        if ($padTo > strlen($png)) {
            $png = str_pad($png, $padTo, "\0");
        }

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function asFinance(): void
    {
        Sanctum::actingAs($this->makeUser('finance'));
    }

    private function recordPayment(Invoice $invoice, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->post("/api/invoices/{$invoice->id}/payments", array_merge([
            'method' => 'transfer',
            'paid_date' => now()->toDateString(),
            'proof' => $this->proof(),
        ], $data), ['Accept' => 'application/json']);
    }

    public function test_invoice_list_requires_manage_invoices_permission(): void
    {
        $this->makeInvoice();

        $this->getJson('/api/invoices')->assertStatus(401);

        Sanctum::actingAs($this->makeUser('trainer'));
        $this->getJson('/api/invoices')->assertStatus(403);

        foreach (['management', 'finance'] as $role) {
            Sanctum::actingAs($this->makeUser($role));
            $this->getJson('/api/invoices')->assertOk();
        }
    }

    public function test_invoice_list_filters_by_status_and_exposes_summary(): void
    {
        $unpaid = $this->makeInvoice(null, ['due_date' => now()->subDays(3)->toDateString()]);
        $this->makeInvoice(null, ['status' => 'paid', 'paid_at' => now()]);
        $this->asFinance();

        $res = $this->getJson('/api/invoices?status=unpaid')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $res->assertJsonPath('data.0.id', $unpaid->id)
            ->assertJsonPath('data.0.student_name', 'Budi Santoso')
            ->assertJsonPath('data.0.is_overdue', true)
            ->assertJsonPath('data.0.days_overdue', 3);
    }

    public function test_transfer_payment_requires_proof(): void
    {
        $invoice = $this->makeInvoice();
        $this->asFinance();

        $this->recordPayment($invoice, ['proof' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proof');
        $this->assertSame(0, Payment::count());
    }

    public function test_cash_payment_does_not_need_proof_and_amount_comes_from_invoice(): void
    {
        $invoice = $this->makeInvoice(null, ['amount' => 640000]);
        $this->asFinance();

        $this->recordPayment($invoice, ['method' => 'tunai', 'proof' => null])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.method', 'tunai');

        $payment = Payment::firstOrFail();
        $this->assertEquals(640000, $payment->amount);
        $this->assertNull($payment->proof_path);
    }

    public function test_proof_must_be_png_or_jpeg_and_at_most_5mb(): void
    {
        $invoice = $this->makeInvoice();
        $this->asFinance();

        $pdf = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');
        $this->recordPayment($invoice, ['proof' => $pdf])->assertStatus(422)->assertJsonValidationErrors('proof');

        $this->recordPayment($invoice, ['proof' => $this->proof('besar.png', 6 * 1024 * 1024)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proof');

        $this->assertSame(0, Payment::count());
    }

    public function test_proof_is_stored_under_generated_name(): void
    {
        $invoice = $this->makeInvoice();
        $this->asFinance();

        $this->recordPayment($invoice, ['proof' => $this->proof('bukti-asli-ortu.png')])->assertCreated();

        $path = Payment::firstOrFail()->proof_path;
        $this->assertStringNotContainsString('bukti-asli-ortu', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_cannot_record_new_payment_while_one_is_pending_or_confirmed(): void
    {
        $invoice = $this->makeInvoice();
        $this->asFinance();

        $this->recordPayment($invoice)->assertCreated();
        $this->recordPayment($invoice)->assertStatus(422)->assertJsonValidationErrors('invoice');

        Payment::query()->update(['status' => 'confirmed']);
        $this->recordPayment($invoice)->assertStatus(422)->assertJsonValidationErrors('invoice');
        $this->assertSame(1, Payment::count());
    }

    public function test_confirm_marks_payment_confirmed_and_invoice_paid(): void
    {
        $invoice = $this->makeInvoice();
        $finance = $this->makeUser('finance');
        Sanctum::actingAs($finance);
        $this->recordPayment($invoice)->assertCreated();
        $payment = Payment::firstOrFail();

        $this->postJson("/api/payments/{$payment->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $payment->refresh();
        $this->assertSame($finance->id, $payment->confirmed_by);
        $this->assertNotNull($payment->confirmed_at);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_confirm_and_reject_require_confirm_permission(): void
    {
        $invoice = $this->makeInvoice();
        $this->asFinance();
        $this->recordPayment($invoice)->assertCreated();
        $payment = Payment::firstOrFail();

        // HR hanya diberi "manage invoices": boleh mencatat, tidak boleh mengonfirmasi.
        $clerk = $this->makeUser('hr');
        $clerk->givePermissionTo('manage invoices');
        Sanctum::actingAs($clerk);
        $this->postJson("/api/payments/{$payment->id}/confirm")->assertStatus(403);
        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'x'])->assertStatus(403);

        Sanctum::actingAs($this->makeUser('trainer'));
        $this->postJson("/api/payments/{$payment->id}/confirm")->assertStatus(403);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_reject_requires_reason_and_allows_a_new_payment(): void
    {
        $invoice = $this->makeInvoice();
        $this->asFinance();
        $this->recordPayment($invoice)->assertCreated();
        $payment = Payment::firstOrFail();

        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'Nominal tidak sesuai'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reject_reason', 'Nominal tidak sesuai');
        $this->assertSame('unpaid', $invoice->fresh()->status);

        $this->recordPayment($invoice)->assertCreated();
        $this->assertSame(2, Payment::count());
    }

    public function test_confirm_and_reject_only_for_pending_payments(): void
    {
        $invoice = $this->makeInvoice();
        $this->asFinance();
        $this->recordPayment($invoice)->assertCreated();
        $payment = Payment::firstOrFail();
        $this->postJson("/api/payments/{$payment->id}/confirm")->assertOk();

        $this->postJson("/api/payments/{$payment->id}/confirm")->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'x'])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_void_requires_reason_and_sends_registration_back_to_verification(): void
    {
        $registration = $this->makeRegistration(['status' => 'terverifikasi'], $this->makeProgram());
        $invoice = $this->makeInvoice($registration);
        $this->asFinance();

        $this->postJson("/api/invoices/{$invoice->id}/void", ['reason' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->postJson("/api/invoices/{$invoice->id}/void", ['reason' => 'Nominal salah'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void');

        $this->assertSame('Nominal salah', $invoice->fresh()->void_reason);
        $this->assertSame('menunggu_verifikasi', $registration->fresh()->status);
    }

    public function test_cannot_void_paid_invoice_or_one_with_pending_payment(): void
    {
        $paid = $this->makeInvoice(null, ['status' => 'paid', 'paid_at' => now()]);
        $withPending = $this->makeInvoice();
        $this->asFinance();
        $this->recordPayment($withPending)->assertCreated();

        $this->postJson("/api/invoices/{$paid->id}/void", ['reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson("/api/invoices/{$withPending->id}/void", ['reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('invoice');
    }

    public function test_voided_invoice_cannot_receive_payment(): void
    {
        $invoice = $this->makeInvoice(null, ['status' => 'void', 'void_reason' => 'salah']);
        $this->asFinance();

        $this->recordPayment($invoice)->assertStatus(422)->assertJsonValidationErrors('invoice');
    }

    public function test_reapproving_after_void_issues_a_new_invoice(): void
    {
        $registration = $this->makeRegistration([], $this->makeProgram());
        Sanctum::actingAs($this->makeUser('management'));

        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();
        $first = Invoice::firstOrFail();
        $this->postJson("/api/invoices/{$first->id}/void", ['reason' => 'Salah paket'])->assertOk();
        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();

        $ym = now()->format('Ym');
        $this->assertSame(["INV/{$ym}/0001", "INV/{$ym}/0002"], Invoice::orderBy('id')->pluck('number')->all());
        $this->assertSame(['void', 'unpaid'], Invoice::orderBy('id')->pluck('status')->all());
    }
}
