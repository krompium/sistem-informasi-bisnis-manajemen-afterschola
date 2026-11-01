<?php

namespace App\Notifications;

use App\Models\ClassSession;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * MGT-37 / SYS-03: jadwal sesi baru atau berubah untuk trainer pelaksana dan orang tua siswa aktif kelas itu.
 * $kind: scheduled (sesi baru), changed (tanggal/jam/mode/trainer berubah), released (trainer lama dilepas dari sesi).
 * $children hanya diisi untuk orang tua (nama anak yang ada di kelas); $newTrainer hanya untuk kind released.
 */
class ClassSessionScheduleNotification extends Notification
{
    private const SUBJECTS = [
        'scheduled' => 'Jadwal sesi baru',
        'changed' => 'Perubahan jadwal sesi',
        'released' => 'Anda tidak lagi bertugas pada sebuah sesi',
    ];

    public function __construct(
        private ClassSession $session,
        private string $kind,
        private ?string $children = null,
        private ?string $newTrainer = null,
    ) {}

    // database lebih dulu: notifikasi in-app tetap tersimpan walau pengiriman email gagal (R-04).
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(self::SUBJECTS[$this->kind].': '.$this->session->classGroup->name)
            ->greeting('Halo,')
            ->line($this->message());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'session_'.$this->kind,
            'message' => $this->message(),
            'class_session_id' => $this->session->id,
            'class_group_id' => $this->session->class_group_id,
        ];
    }

    private function when(): string
    {
        return $this->session->date->format('d/m/Y').' '.substr($this->session->start_time, 0, 5).'–'.substr($this->session->end_time, 0, 5);
    }

    private function message(): string
    {
        $class = $this->session->classGroup->name;
        $meeting = "pertemuan ke-{$this->session->meeting_no}";

        if ($this->kind === 'released') {
            return "Sesi {$class}, {$meeting} ({$this->when()}) dialihkan ke {$this->newTrainer}. Anda tidak lagi bertugas pada sesi ini.";
        }

        $mode = $this->session->mode === 'online' ? 'Online' : 'Onsite';
        $title = $this->kind === 'scheduled' ? 'Jadwal sesi baru' : 'Jadwal sesi berubah';
        $message = "{$title}: {$class}, {$meeting}, {$this->when()} ({$mode})";

        // Orang tua perlu tahu untuk anak yang mana dan siapa pelaksananya (bisa trainer pengganti).
        if ($this->children !== null) {
            $message .= " untuk {$this->children}, trainer {$this->session->trainer->name}";
        }

        return $message.'.';
    }
}
