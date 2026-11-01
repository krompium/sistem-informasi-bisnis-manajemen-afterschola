<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

// SYS-03: pemberitahuan alur pendaftaran ke Orangtua. $event: trial_result (hasil trial siap), revision (pendaftaran perlu revisi).
class EnrollmentNotification extends Notification
{
    public function __construct(private Lead $lead, private string $event, private ?string $note = null) {}

    /**
     * Kirim ke Orangtua pemilik anak. Kegagalan kirim tidak membatalkan proses utama: dicatat (NFR-07).
     * Lead tanpa akun orang tua (data lama) dilewati.
     */
    public static function send(Lead $lead, string $event, ?string $note = null): void
    {
        $parent = $lead->parent;
        if (! $parent) {
            return;
        }

        try {
            $parent->notify(new self($lead, $event, $note));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi pendaftaran.', ['lead_id' => $lead->id, 'event' => $event, 'error' => $e->getMessage()]);
        }
    }

    // database lebih dulu: notifikasi in-app tetap tersimpan walau pengiriman email gagal (R-04).
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->message())->greeting("Halo {$notifiable->name},");

        if ($this->event === 'revision') {
            $mail->line("Admin meminta perbaikan data pendaftaran {$this->lead->name}.")->line("Catatan: {$this->note}");
        } else {
            $mail->line("Hasil trial {$this->lead->name} sudah dapat dilihat. Silakan lihat hasilnya dan putuskan apakah ingin melanjutkan.");
        }

        return $mail->action('Buka Portal', rtrim(config('app.frontend_url'), '/').'/portal/anak/'.$this->lead->id);
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'enrollment_'.$this->event, 'message' => $this->message(), 'lead_id' => $this->lead->id];
    }

    private function message(): string
    {
        return $this->event === 'revision'
            ? "Pendaftaran {$this->lead->name} perlu revisi"
            : "Hasil trial {$this->lead->name} sudah siap";
    }
}
