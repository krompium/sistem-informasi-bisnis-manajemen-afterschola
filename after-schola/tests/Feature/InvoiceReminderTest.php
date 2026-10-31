<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesBranchFixtures;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 lanjutan (5d, SYS-04, BR-08): reminder berkala untuk invoice unpaid yang lewat jatuh tempo.
 * Interval mengikuti PaymentSetting.reminder_interval_days (default 3 hari).
 */
class InvoiceReminderTest extends TestCase
{
    use MakesBranchFixtures, MakesFinanceFixtures {
        MakesBranchFixtures::seedRoles insteadof MakesFinanceFixtures;
    }
    use RefreshDatabase;

    private $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->program = $this->makeProgram(500000);
    }

    /** @return array{0: Invoice, 1: ?User} invoice (overdue 5 hari secara default) dan akun ortunya */
    private function invoiceWithParent(array $invoice = [], bool $hasAccount = true, ?string $legacyEmail = null): array
    {
        $parent = null;
        $registrationData = ['status' => 'terverifikasi'];
        if ($hasAccount) {
            $parent = $this->makeStaff('parent');
            $lead = Lead::create([
                'name' => 'Budi', 'education_level' => 'smp', 'origin_school' => 'SMP 5',
                'parent_email' => $parent->email, 'parent_id' => $parent->id,
                'program_id' => $this->program->id, 'status' => 'trial_scheduled',
            ]);
            $registrationData += ['lead_id' => $lead->id, 'parent_email' => $parent->email];
        } else {
            $registrationData += ['parent_email' => $legacyEmail];
        }

        $invoice = $this->makeInvoice(
            $this->makeRegistration($registrationData, $this->program),
            $invoice + ['due_date' => now()->subDays(5)->toDateString()]
        );

        return [$invoice, $parent];
    }

    private function runReminders(): void
    {
        $this->artisan('invoices:send-reminders')->assertSuccessful();
    }

    public function test_only_unpaid_overdue_invoices_without_pending_payment_get_reminders(): void
    {
        Notification::fake();
        [, $overdue] = $this->invoiceWithParent();
        [, $notDue] = $this->invoiceWithParent(['due_date' => now()->addDays(3)->toDateString()]);
        [, $paid] = $this->invoiceWithParent(['status' => 'paid', 'paid_at' => now()]);
        [, $void] = $this->invoiceWithParent(['status' => 'void']);
        [$awaiting, $awaitingParent] = $this->invoiceWithParent();
        Payment::create([
            'invoice_id' => $awaiting->id, 'amount' => $awaiting->amount, 'method' => 'transfer',
            'paid_date' => now()->toDateString(), 'status' => 'pending',
        ]);

        $this->runReminders();

        Notification::assertSentTo($overdue, InvoiceReminderNotification::class);
        foreach ([$notDue, $paid, $void, $awaitingParent] as $parent) {
            Notification::assertNotSentTo($parent, InvoiceReminderNotification::class);
        }
    }

    public function test_reminder_interval_is_respected_and_last_reminded_at_is_recorded(): void
    {
        Notification::fake();
        $this->assertSame(3, PaymentSetting::current()->reminder_interval_days);
        [$fresh, $freshParent] = $this->invoiceWithParent(['last_reminded_at' => now()->subDay()]);
        [$due, $dueParent] = $this->invoiceWithParent(['last_reminded_at' => now()->subDays(3)]);
        [$never, $neverParent] = $this->invoiceWithParent();

        $this->runReminders();
        $this->runReminders(); // dijalankan ulang di hari yang sama tidak boleh mengirim dobel

        Notification::assertNotSentTo($freshParent, InvoiceReminderNotification::class);
        Notification::assertSentToTimes($dueParent, InvoiceReminderNotification::class, 1);
        Notification::assertSentToTimes($neverParent, InvoiceReminderNotification::class, 1);
        $this->assertTrue($never->fresh()->last_reminded_at->isToday());
        $this->assertTrue($due->fresh()->last_reminded_at->isToday());
        $this->assertTrue($fresh->fresh()->last_reminded_at->lt(now()->subHours(23)));
    }

    public function test_parent_account_gets_in_app_and_email_while_legacy_registration_gets_email_only(): void
    {
        Notification::fake();
        [$invoice, $parent] = $this->invoiceWithParent();
        $this->invoiceWithParent([], false, 'ortu@legacy.test');
        [$orphan] = $this->invoiceWithParent([], false, null);

        $this->runReminders();

        Notification::assertSentTo($parent, InvoiceReminderNotification::class, function ($notification, $channels) use ($invoice, $parent) {
            $mail = implode(' ', $notification->toMail($parent)->introLines);

            return $channels === ['database', 'mail']
                && str_contains($mail, $invoice->number)
                && str_contains($mail, 'Rp 500.000')
                && $notification->toArray($parent)['invoice_id'] === $invoice->id;
        });
        Notification::assertSentOnDemand(
            InvoiceReminderNotification::class,
            fn ($notification, $channels, $notifiable) => $channels === ['mail'] && $notifiable->routes['mail'] === 'ortu@legacy.test'
        );
        // Tanpa akun dan tanpa email: tidak ada yang bisa dikirimi, jadi dicoba lagi besok.
        $this->assertNull($orphan->fresh()->last_reminded_at);
    }

    public function test_failed_delivery_does_not_block_other_invoices_and_is_retried_next_run(): void
    {
        [$broken, $brokenParent] = $this->invoiceWithParent();
        [$healthy, $healthyParent] = $this->invoiceWithParent();
        Event::listen(NotificationSending::class, function (NotificationSending $event) use ($brokenParent) {
            if ($event->notifiable->is($brokenParent)) {
                throw new \RuntimeException('smtp down');
            }
        });

        $this->runReminders();

        $this->assertSame(1, $healthyParent->notifications()->count());
        $this->assertNotNull($healthy->fresh()->last_reminded_at);
        $this->assertNull($broken->fresh()->last_reminded_at);
    }

    public function test_command_is_scheduled_daily(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('invoices:send-reminders', Artisan::output());
    }
}
