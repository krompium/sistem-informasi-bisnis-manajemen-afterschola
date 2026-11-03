<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * MGT-37 / SYS-03: orang tua diberi tahu anaknya sudah ditempatkan di kelas, beserta jadwal terdekat.
 * $upcoming: daftar teks "d/m/Y HH:MM–HH:MM" yang disiapkan ScheduleNotifier.
 * Teks dibekukan saat dibuat (bukan membaca model nanti) supaya tidak terpengaruh pemuatan ulang relasi.
 */
class StudentPlacedNotification extends Notification
{
    private string $student;

    private string $class;

    private string $classDetail;

    private int $enrollmentId;

    private int $classGroupId;

    /** @param list<string> $upcoming */
    public function __construct(Enrollment $enrollment, private array $upcoming)
    {
        $group = $enrollment->classGroup;
        $this->student = $enrollment->student->name;
        $this->class = $group->name;
        $this->classDetail = "{$group->program->name}, {$group->branch->name}";
        $this->enrollmentId = $enrollment->id;
        $this->classGroupId = $group->id;
    }

    // database lebih dulu: notifikasi in-app tetap tersimpan walau pengiriman email gagal (R-04).
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->student} ditempatkan di kelas {$this->class}")
            ->greeting('Halo,')
            ->line("{$this->student} sudah ditempatkan di kelas {$this->class} ({$this->classDetail}).")
            ->line($this->upcoming
                ? 'Jadwal terdekat: '.implode('; ', $this->upcoming).'.'
                : 'Jadwal sesi akan kami informasikan begitu ditetapkan.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'student_placed',
            'message' => "{$this->student} ditempatkan di kelas {$this->class}",
            'enrollment_id' => $this->enrollmentId,
            'class_group_id' => $this->classGroupId,
        ];
    }
}
