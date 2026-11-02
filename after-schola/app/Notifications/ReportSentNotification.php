<?php

namespace App\Notifications;

use App\Models\Evaluation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SYS-03: raport anak terbit (disetujui koordinator) -> orang tua. Teks dibekukan saat dibuat, seperti StudentPlacedNotification.
 * TODO(nanti): tautan ke halaman raport di portal ortu (ORT-11) setelah portal ada.
 */
class ReportSentNotification extends Notification
{
    private string $student;

    private string $class;

    private string $period;

    private int $evaluationId;

    public function __construct(Evaluation $evaluation)
    {
        $this->student = $evaluation->enrollment->student->name;
        $this->class = $evaluation->enrollment->classGroup->name;
        $this->period = $evaluation->period;
        $this->evaluationId = $evaluation->id;
    }

    // database lebih dulu: notifikasi in-app tetap tersimpan walau email gagal (R-04).
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Raport {$this->student} periode {$this->period} sudah terbit")
            ->greeting('Halo,')
            ->line("Laporan perkembangan {$this->student} di kelas {$this->class} untuk periode {$this->period} sudah terbit.")
            ->line('Silakan buka portal orang tua After Schola untuk melihatnya.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_sent',
            'message' => "Raport {$this->student} periode {$this->period} sudah terbit",
            'evaluation_id' => $this->evaluationId,
        ];
    }
}
