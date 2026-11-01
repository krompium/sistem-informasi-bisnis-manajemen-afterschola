<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Notifications\InvoiceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * SYS-03/04, BR-08: notifikasi invoice ke Orangtua dan reminder harian untuk invoice lewat jatuh tempo.
 */
class InvoiceNotificationTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
    }

    private function overdueInvoice(array $overrides = []): Invoice
    {
        return $this->makeInvoice(
            $this->makeParentRegistration($this->newBranch('B'.uniqid())),
            array_merge(['due_date' => now()->subDay()->toDateString()], $overrides),
        );
    }

    private function parentOf(Invoice $invoice)
    {
        return $invoice->registration->lead->parent;
    }

    private function kind(Invoice $invoice, string $event): string
    {
        return (new InvoiceNotification($invoice, $event))->toArray($this->parentOf($invoice))['kind'];
    }

    public function test_events_notify_the_parent(): void
    {
        $registration = $this->makeParentRegistration($this->newBranch('BDG'), ['status' => 'menunggu_verifikasi']);
        Sanctum::actingAs($this->makeUser('management'));

        $this->postJson("/api/registrations/{$registration->id}/approve")->assertOk();
        $invoice = Invoice::firstOrFail();
        $parent = $this->parentOf($invoice);
        Notification::assertSentTo($parent, InvoiceNotification::class, fn ($n) => $n->toArray($parent)['kind'] === 'invoice_issued');

        $pending = $this->makePayment($invoice);
        $this->postJson("/api/payments/{$pending->id}/confirm")->assertOk();
        Notification::assertSentTo($parent, InvoiceNotification::class, fn ($n) => $n->toArray($parent)['kind'] === 'invoice_confirmed');

        $second = $this->overdueInvoice();
        $rejected = $this->makePayment($second);
        $this->postJson("/api/payments/{$rejected->id}/reject", ['reason' => 'Bukti buram'])->assertOk();
        Notification::assertSentTo($this->parentOf($second), InvoiceNotification::class, fn ($n) => $n->toArray($this->parentOf($second))['kind'] === 'invoice_rejected');
    }

    public function test_command_reminds_only_overdue_unpaid_invoices_without_pending_payment(): void
    {
        $due = $this->overdueInvoice();
        $notDue = $this->overdueInvoice(['due_date' => now()->addDays(2)->toDateString()]);
        $paid = $this->overdueInvoice(['status' => 'paid', 'paid_at' => now()]);
        $void = $this->overdueInvoice(['status' => 'void']);
        $waiting = $this->overdueInvoice();
        $this->makePayment($waiting); // bukti sudah dikirim, tinggal menunggu Keuangan

        $this->artisan('invoices:send-reminders')->assertSuccessful();

        Notification::assertSentTo($this->parentOf($due), InvoiceNotification::class, fn ($n) => $n->toArray($this->parentOf($due))['kind'] === 'invoice_reminder');
        foreach ([$notDue, $paid, $void, $waiting] as $invoice) {
            Notification::assertNotSentTo($this->parentOf($invoice), InvoiceNotification::class);
        }
        $this->assertNotNull($due->fresh()->last_reminded_at);
    }

    public function test_reminder_respects_interval_from_payment_settings(): void
    {
        $recent = $this->overdueInvoice(['last_reminded_at' => now()->subDay()]);  // interval default 3 hari
        $old = $this->overdueInvoice(['last_reminded_at' => now()->subDays(3)]);

        $this->artisan('invoices:send-reminders')->assertSuccessful();

        Notification::assertNotSentTo($this->parentOf($recent), InvoiceNotification::class);
        Notification::assertSentTo($this->parentOf($old), InvoiceNotification::class);
    }

    public function test_invoice_without_parent_is_skipped_without_error(): void
    {
        $invoice = $this->makeInvoice(null, ['due_date' => now()->subDay()->toDateString()]); // pendaftaran lama tanpa lead

        $this->artisan('invoices:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($invoice->fresh()->last_reminded_at);
    }

    public function test_in_app_kinds_and_mail_render_for_all_events(): void
    {
        $invoice = $this->overdueInvoice();
        $parent = $this->parentOf($invoice);
        $kinds = [];

        foreach (['issued', 'reminder', 'confirmed', 'rejected'] as $event) {
            $notification = new InvoiceNotification($invoice, $event, 'Bukti buram');
            $kinds[] = $notification->toArray($parent)['kind'];
            $this->assertStringContainsString($invoice->number, $notification->toMail($parent)->subject);
        }

        $this->assertSame(['invoice_issued', 'invoice_reminder', 'invoice_confirmed', 'invoice_rejected'], $kinds);
    }
}
