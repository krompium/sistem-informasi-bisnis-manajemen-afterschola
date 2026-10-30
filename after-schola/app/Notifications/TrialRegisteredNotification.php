<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// PUB-05: konfirmasi pendaftaran trial + tautan atur password untuk akun Orangtua yang baru dibuat.
class TrialRegisteredNotification extends Notification
{
    public function __construct(private Lead $lead, private string $token) {}

    // database lebih dulu: notifikasi in-app tetap tersimpan walau pengiriman email gagal (R-04).
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $this->lead->loadMissing('trialSlot.program', 'trialSlot.branch');
        $slot = $this->lead->trialSlot;
        $when = $slot->starts_at->copy()->timezone(config('app.display_timezone'))->locale('id')->translatedFormat('l, j F Y H:i').' WIB';

        return (new MailMessage)
            ->subject('Pendaftaran trial diterima')
            ->greeting("Halo {$notifiable->name},")
            ->line("Pendaftaran trial untuk {$this->lead->name} sudah kami terima.")
            ->line("Jadwal: {$slot->program->name}, {$slot->branch->name}, {$when}.")
            ->line('Atur password akun Anda untuk melihat hasil trial dan melanjutkan pendaftaran:')
            ->action('Atur Password', $notifiable->passwordResetUrl($this->token))
            ->line('Tautan berlaku '.config('auth.passwords.users.expire').' menit.');
    }

    // Tanpa token: isi notifikasi in-app tidak boleh memuat tautan reset.
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'trial_registered',
            'message' => "Pendaftaran trial {$this->lead->name} diterima",
            'lead_id' => $this->lead->id,
        ];
    }
}
