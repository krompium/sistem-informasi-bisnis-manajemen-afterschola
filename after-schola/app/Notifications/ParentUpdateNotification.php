<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// SYS-03 / ORT-13: kabar status untuk orang tua (hasil trial siap, revisi pendaftaran, verifikasi dan invoice, pembayaran).
// $data (mis. lead_id, invoice_id) ikut disimpan di notifikasi in-app agar portal bisa membuka halaman terkait.
class ParentUpdateNotification extends Notification
{
    public function __construct(private string $kind, private string $subject, private string $message, private array $data = []) {}

    // database lebih dulu: notifikasi in-app tetap tersimpan walau pengiriman email gagal (R-04).
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting("Halo {$notifiable->name},")
            ->line($this->message)
            ->action('Buka Portal', rtrim(config('app.frontend_url'), '/').'/portal');
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'message' => $this->message] + $this->data;
    }
}
