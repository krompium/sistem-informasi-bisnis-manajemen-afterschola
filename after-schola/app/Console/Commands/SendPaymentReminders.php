<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Notifications\PaymentReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// SYS-04 / BR-08: invoice unpaid yang lewat jatuh tempo diingatkan tiap reminder_interval_days.
// Overdue tidak disimpan, selalu diturunkan dari due_date.
class SendPaymentReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Kirim pengingat pembayaran ke orang tua untuk invoice yang lewat jatuh tempo';

    public function handle(): int
    {
        $interval = PaymentSetting::current()->reminder_interval_days;
        $sent = 0;
        $skipped = 0;

        Invoice::where('status', 'unpaid')
            ->whereDate('due_date', '<', today())
            // Bukti sudah diunggah: tinggal menunggu konfirmasi Keuangan, bukan salah orang tua.
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'pending'))
            ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDays($interval)))
            ->with('registration.lead.parent')
            ->chunkById(100, function ($invoices) use (&$sent, &$skipped) {
                foreach ($invoices as $invoice) {
                    $parent = $invoice->registration?->lead?->parent;
                    if (! $parent) {
                        $skipped++; // pendaftaran lama tanpa akun orang tua
                        continue;
                    }

                    // Gagal kirim email dicatat (NFR-07); notifikasi in-app sudah tersimpan, jadi tetap dihitung terkirim.
                    try {
                        $parent->notify(new PaymentReminderNotification($invoice));
                    } catch (\Throwable $e) {
                        Log::error('Gagal mengirim pengingat pembayaran.', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
                    }
                    $invoice->update(['last_reminded_at' => now()]);
                    $sent++;
                }
            });

        $this->info("Pengingat terkirim: {$sent}, dilewati (tanpa akun orang tua): {$skipped}.");

        return self::SUCCESS;
    }
}
