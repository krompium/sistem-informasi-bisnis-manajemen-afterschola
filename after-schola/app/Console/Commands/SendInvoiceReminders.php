<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * SYS-04 / BR-08: reminder untuk invoice unpaid yang lewat jatuh tempo, berulang tiap
 * PaymentSetting.reminder_interval_days. Overdue sendiri diturunkan dari due_date (tidak disimpan).
 * Invoice yang sudah punya pembayaran menunggu konfirmasi dilewati: ortu sudah membayar.
 */
class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Kirim reminder pembayaran untuk invoice yang lewat jatuh tempo';

    public function handle(): int
    {
        $interval = PaymentSetting::current()->reminder_interval_days;
        $count = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        Invoice::where('status', 'unpaid')
            ->where('due_date', '<', today()->toDateString())
            ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDays($interval)))
            ->whereDoesntHave('payments', fn ($p) => $p->where('status', 'pending'))
            ->with('registration.lead.parent')
            ->chunkById(100, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    $registration = $invoice->registration;
                    $recipient = $registration->lead?->parent
                        ?? ($registration->parent_email ? Notification::route('mail', $registration->parent_email) : null);

                    if (! $recipient) {
                        $count['skipped']++;

                        continue;
                    }

                    // Satu kegagalan kirim tidak boleh menghentikan invoice lain; last_reminded_at tetap kosong
                    // supaya dicoba lagi pada jalan berikutnya (NFR-07: kegagalan tercatat di log).
                    try {
                        $recipient->notify(new InvoiceReminderNotification($invoice));
                    } catch (Throwable $e) {
                        report($e);
                        $count['failed']++;

                        continue;
                    }

                    $invoice->update(['last_reminded_at' => now()]);
                    $count['sent']++;
                }
            });

        $this->info("Reminder terkirim: {$count['sent']}, tanpa penerima: {$count['skipped']}, gagal: {$count['failed']}");

        return self::SUCCESS;
    }
}
