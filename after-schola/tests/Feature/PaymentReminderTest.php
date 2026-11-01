<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PaymentSetting;
use App\Models\Program;
use App\Models\User;
use App\Notifications\PaymentReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesFinanceFixtures;
use Tests\TestCase;

/**
 * Modul 5 (SYS-04, BR-08, KEU-09): scheduler harian mengingatkan orang tua untuk invoice unpaid
 * yang lewat jatuh tempo, diulang tiap reminder_interval_days.
 */
class PaymentReminderTest extends TestCase
{
    use MakesFinanceFixtures, RefreshDatabase;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->program = $this->makeProgram();
    }

    private function parent(): User
    {
        $parent = User::create([
            'name' => 'Ibu Budi',
            'email' => 'ortu_'.uniqid().'@test.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $parent->assignRole('parent');

        return $parent;
    }

    /** Invoice milik $parent; default unpaid dan lewat jatuh tempo 2 hari. */
    private function invoiceFor(?User $parent, array $overrides = []): Invoice
    {
        $lead = $parent ? Lead::create([
            'name' => 'Budi',
            'education_level' => 'sd',
            'origin_school' => 'SD Negeri 1',
            'parent_email' => $parent->email,
            'parent_id' => $parent->id,
            'program_id' => $this->program->id,
            'status' => 'trial_scheduled',
        ]) : null;
        $registration = $this->makeRegistration(['status' => 'terverifikasi', 'lead_id' => $lead?->id], $this->program);

        return $this->makeInvoice($registration, $overrides + ['due_date' => now()->subDays(2)->toDateString()]);
    }

    private function runReminders(): void
    {
        $this->artisan('invoices:send-reminders')->assertSuccessful();
    }

    public function test_overdue_unpaid_invoice_reminds_the_parent_and_records_the_time(): void
    {
        Notification::fake();
        $parent = $this->parent();
        $invoice = $this->invoiceFor($parent);

        $this->runReminders();

        Notification::assertSentTo($parent, PaymentReminderNotification::class);
        $this->assertNotNull($invoice->fresh()->last_reminded_at);
    }

    public function test_reminder_is_stored_in_app_with_a_readable_message(): void
    {
        $parent = $this->parent();
        $invoice = $this->invoiceFor($parent);

        $this->runReminders();

        $notification = $parent->notifications()->sole();
        $this->assertSame('payment_reminder', $notification->data['kind']);
        $this->assertSame($invoice->id, $notification->data['invoice_id']);
        $this->assertStringContainsString($invoice->number, $notification->data['message']);
    }

    public function test_invoice_not_yet_overdue_is_not_reminded(): void
    {
        Notification::fake();
        $parent = $this->parent();
        $this->invoiceFor($parent, ['due_date' => now()->toDateString()]);
        $this->invoiceFor($parent, ['due_date' => now()->addDays(3)->toDateString()]);

        $this->runReminders();

        Notification::assertNothingSent();
    }

    public function test_paid_and_void_invoices_are_not_reminded(): void
    {
        Notification::fake();
        $parent = $this->parent();
        $this->invoiceFor($parent, ['status' => 'paid', 'paid_at' => now()]);
        $this->invoiceFor($parent, ['status' => 'void']);

        $this->runReminders();

        Notification::assertNothingSent();
    }

    public function test_reminder_repeats_only_after_the_configured_interval(): void
    {
        Notification::fake();
        $parent = $this->parent(); // interval default 3 hari
        $recent = $this->invoiceFor($parent, ['last_reminded_at' => now()->subDays(2)]);
        $due = $this->invoiceFor($parent, ['last_reminded_at' => now()->subDays(3)]);

        $this->runReminders();

        Notification::assertSentToTimes($parent, PaymentReminderNotification::class, 1);
        $this->assertTrue($recent->fresh()->last_reminded_at->lt(now()->subDay()));
        $this->assertTrue($due->fresh()->last_reminded_at->gt(now()->subMinute()));
    }

    public function test_interval_follows_payment_settings(): void
    {
        Notification::fake();
        PaymentSetting::current()->update(['reminder_interval_days' => 1]);
        $parent = $this->parent();
        $this->invoiceFor($parent, ['last_reminded_at' => now()->subDay()]);

        $this->runReminders();

        Notification::assertSentTo($parent, PaymentReminderNotification::class);
    }

    public function test_invoice_waiting_for_payment_confirmation_is_not_reminded(): void
    {
        Notification::fake();
        $parent = $this->parent();
        $invoice = $this->invoiceFor($parent);
        $invoice->payments()->create([
            'amount' => $invoice->amount,
            'method' => 'transfer',
            'paid_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->runReminders();

        Notification::assertNothingSent();
    }

    public function test_invoice_without_a_parent_account_is_skipped_without_error(): void
    {
        Notification::fake();
        $invoice = $this->invoiceFor(null);

        $this->runReminders();

        Notification::assertNothingSent();
        $this->assertNull($invoice->fresh()->last_reminded_at);
    }

    public function test_command_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('invoices:send-reminders');
    }
}
