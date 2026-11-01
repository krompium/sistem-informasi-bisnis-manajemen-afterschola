<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

// SYS-03: notifikasi invoice ke Orangtua. $event: issued, reminder, confirmed, rejected.
class InvoiceNotification extends Notification
{
    public function __construct(private Invoice $invoice, private string $event, private ?string $reason = null) {}

    /**
     * Kirim ke Orangtua pemilik invoice. Kegagalan kirim tidak membatalkan proses utama: dicatat (NFR-07).
     * Mengembalikan false bila invoice tidak punya Orangtua (pendaftaran lama tanpa lead).
     */
    public static function send(Invoice $invoice, string $event, ?string $reason = null): bool
    {
        $parent = $invoice->parentUser();
        if (! $parent) {
            return false;
        }

        try {
            $parent->notify(new self($invoice, $event, $reason));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi invoice.', ['invoice_id' => $invoice->id, 'event' => $event, 'error' => $e->getMessage()]);
        }

        return true;
    }

    // database lebih dulu: notifikasi in-app tetap tersimpan walau pengiriman email gagal (R-04).
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->greeting("Halo {$notifiable->name},");

        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail->action('Buka Portal', rtrim(config('app.frontend_url'), '/').'/portal');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'invoice_'.$this->event,
            'message' => $this->title(),
            'invoice_id' => $this->invoice->id,
        ];
    }

    private function title(): string
    {
        $number = $this->invoice->number;

        return match ($this->event) {
            'issued' => "Invoice {$number} terbit",
            'reminder' => "Pengingat pembayaran invoice {$number}",
            'confirmed' => "Pembayaran invoice {$number} dikonfirmasi",
            'rejected' => "Pembayaran invoice {$number} ditolak",
        };
    }

    /** @return array<int, string> */
    private function lines(): array
    {
        $amount = 'Rp '.number_format((float) $this->invoice->amount, 0, ',', '.');
        $due = $this->invoice->due_date->locale('id')->translatedFormat('j F Y');

        if ($this->event === 'confirmed') {
            return ["Pembayaran sebesar {$amount} sudah kami terima dan dikonfirmasi. Terima kasih."];
        }
        if ($this->event === 'rejected') {
            return ["Bukti pembayaran invoice {$this->invoice->number} tidak dapat kami terima.", "Alasan: {$this->reason}", 'Silakan unggah ulang bukti pembayaran yang benar.'];
        }

        $lines = [
            $this->event === 'issued'
                ? "Pendaftaran sudah terverifikasi. Silakan lakukan pembayaran sebesar {$amount} paling lambat {$due}."
                : "Invoice {$this->invoice->number} sebesar {$amount} sudah melewati jatuh tempo ({$due}). Mohon segera lakukan pembayaran.",
        ];
        $setting = PaymentSetting::current();
        foreach ([$setting->bank_account, $setting->payment_instructions] as $info) {
            if ($info) {
                $lines[] = $info;
            }
        }

        return $lines;
    }
}
