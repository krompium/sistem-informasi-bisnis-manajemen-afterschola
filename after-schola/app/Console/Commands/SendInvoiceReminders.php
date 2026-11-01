<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Notifications\InvoiceNotification;
use Illuminate\Console\Command;

// SYS-04 / BR-08: reminder berkala untuk invoice yang melewati jatuh tempo. Dijadwalkan harian (routes/console.php).
class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Kirim reminder pembayaran untuk invoice unpaid yang melewati jatuh tempo';

    public function handle(): int
    {
        $interval = PaymentSetting::current()->reminder_interval_days;
        $sent = 0;

        Invoice::where('status', 'unpaid')
            ->where('due_date', '<', today()->toDateString())
            ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDays($interval)))
            // Bukti sudah dikirim dan tinggal menunggu Keuangan: tidak perlu diingatkan.
            ->whereDoesntHave('payments', fn ($q) => $q->whereIn('status', ['pending', 'confirmed']))
            ->with('registration.lead.parent')
            ->chunkById(100, function ($invoices) use (&$sent) {
                foreach ($invoices as $invoice) {
                    if (InvoiceNotification::send($invoice, 'reminder')) {
                        $invoice->update(['last_reminded_at' => now()]);
                        $sent++;
                    }
                }
            });

        $this->info("Reminder terkirim: {$sent}");

        return self::SUCCESS;
    }
}
