<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// SYS-04 / BR-08: pengingat pembayaran untuk invoice yang lewat jatuh tempo.
class InvoiceReminderNotification extends Notification
{
    public function __construct(private Invoice $invoice) {}

    // Akun Orangtua: in-app lebih dulu (tetap tersimpan walau email gagal, R-04). Pendaftaran lama tanpa akun: email saja.
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $registration = $this->invoice->registration;
        $amount = number_format((float) $this->invoice->amount, 0, ',', '.');
        $setting = PaymentSetting::current();

        $mail = (new MailMessage)
            ->subject("Pengingat pembayaran invoice {$this->invoice->number}")
            ->greeting("Halo {$registration->parent_name},")
            ->line("Invoice {$this->invoice->number} untuk {$registration->student_name} sebesar Rp {$amount} sudah lewat jatuh tempo {$this->invoice->daysOverdue()} hari.")
            ->line('Mohon segera lakukan pembayaran. Abaikan pesan ini bila Anda sudah membayar.');

        if ($setting->payment_instructions) {
            $mail->line($setting->payment_instructions);
        }
        if ($setting->bank_account) {
            $mail->line("Rekening tujuan: {$setting->bank_account}");
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'invoice_reminder',
            'message' => "Invoice {$this->invoice->number} sudah lewat jatuh tempo",
            'invoice_id' => $this->invoice->id,
        ];
    }
}
