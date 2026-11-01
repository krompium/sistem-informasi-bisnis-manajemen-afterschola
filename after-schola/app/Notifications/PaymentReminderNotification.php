<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// SYS-04 / BR-08: pengingat invoice yang lewat jatuh tempo.
class PaymentReminderNotification extends Notification
{
    public function __construct(private Invoice $invoice) {}

    // database lebih dulu: notifikasi in-app tetap tersimpan walau pengiriman email gagal (R-04).
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $registration = $this->invoice->registration;
        $amount = 'Rp '.number_format((float) $this->invoice->amount, 0, ',', '.');
        $instructions = PaymentSetting::current()->payment_instructions;

        $mail = (new MailMessage)
            ->subject("Pengingat pembayaran {$this->invoice->number}")
            ->greeting("Halo {$notifiable->name},")
            ->line("Invoice {$this->invoice->number} untuk {$registration->student_name} sebesar {$amount} sudah melewati jatuh tempo ({$this->invoice->due_date->format('d/m/Y')}).")
            ->line('Mohon segera lakukan pembayaran.');

        if ($instructions) {
            $mail->line($instructions);
        }

        return $mail->action('Buka Portal', rtrim(config('app.frontend_url'), '/').'/portal');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'payment_reminder',
            'message' => "Invoice {$this->invoice->number} sudah lewat jatuh tempo, mohon segera dibayar",
            'invoice_id' => $this->invoice->id,
        ];
    }
}
