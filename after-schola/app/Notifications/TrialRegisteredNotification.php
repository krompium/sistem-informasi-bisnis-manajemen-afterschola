<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

// PUB-05: konfirmasi pendaftaran trial + tautan atur password untuk akun Orangtua yang baru dibuat.
// Tanpa token (orang tua lama menambah anak, ORT-03): hanya konfirmasi jadwal.
class TrialRegisteredNotification extends Notification
{
    public function __construct(private Lead $lead, private ?string $token = null) {}

    /**
     * Kegagalan kirim tidak membatalkan pendaftaran: dicatat (NFR-07), notifikasi in-app tetap tersimpan.
     * TODO(nanti): antrekan (ShouldQueue) bila pengiriman email memperlambat respons form.
     */
    public static function send(Lead $lead, User $parent, bool $withPasswordLink = false): void
    {
        try {
            $parent->notify(new self($lead, $withPasswordLink ? Password::createToken($parent) : null));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim konfirmasi pendaftaran trial.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

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

        $mail = (new MailMessage)
            ->subject('Pendaftaran trial diterima')
            ->greeting("Halo {$notifiable->name},")
            ->line("Pendaftaran trial untuk {$this->lead->name} sudah kami terima.")
            ->line("Jadwal: {$slot->program->name}, {$slot->branch->name}, {$when}.");

        if ($this->token) {
            $mail->line('Atur password akun Anda untuk melihat hasil trial dan melanjutkan pendaftaran:')
                ->action('Atur Password', $notifiable->passwordResetUrl($this->token))
                ->line('Tautan berlaku '.config('auth.passwords.users.expire').' menit.');
        }

        return $mail;
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
