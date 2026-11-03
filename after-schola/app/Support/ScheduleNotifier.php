<?php

namespace App\Support;

use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ClassSessionScheduleNotification;
use App\Notifications\StudentPlacedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Notifikasi jadwal (Modul 6d, MGT-37): sesi baru atau berubah -> trainer dan orang tua siswa aktif kelas;
 * penempatan siswa -> orang tua anak itu. Gagal kirim tidak boleh menggagalkan request atau penerima lain (NFR-07).
 * TODO(nanti): antrekan (ShouldQueue) bila kelas besar memperlambat respons; butuh queue worker di server.
 */
class ScheduleNotifier
{
    /** Hanya perubahan ini yang berarti bagi penerima; catatan dan nomor pertemuan tidak memicu notifikasi. */
    public static function signature(ClassSession $session): array
    {
        return [
            $session->date?->toDateString(),
            substr((string) $session->start_time, 0, 5),
            substr((string) $session->end_time, 0, 5),
            $session->mode,
            $session->trainer_id,
        ];
    }

    public function sessionScheduled(ClassSession $session): void
    {
        $session->load('classGroup', 'trainer');

        $this->deliver($session->trainer, new ClassSessionScheduleNotification($session, 'scheduled'));
        $this->notifyParents($session, 'scheduled');
    }

    /** @param array $before hasil signature() sebelum sesi diubah */
    public function sessionChanged(ClassSession $session, array $before): void
    {
        if (self::signature($session) === $before) {
            return;
        }

        $session->load('classGroup', 'trainer');

        $this->deliver($session->trainer, new ClassSessionScheduleNotification($session, 'changed'));
        $previous = $before[4] !== $session->trainer_id ? User::find($before[4]) : null;
        if ($previous) {
            $this->deliver($previous, new ClassSessionScheduleNotification($session, 'released', null, $session->trainer->name));
        }
        $this->notifyParents($session, 'changed');
    }

    public function studentPlaced(Enrollment $enrollment): void
    {
        $enrollment->load('student.parent', 'classGroup.program', 'classGroup.branch');

        if (! $parent = $enrollment->student->parent) {
            return;
        }

        $upcoming = ClassSession::where('class_group_id', $enrollment->class_group_id)
            ->whereDate('date', '>=', now(config('app.display_timezone'))->toDateString())
            ->orderBy('date')->orderBy('start_time')->limit(3)->get()
            ->map(fn (ClassSession $s) => $s->date->format('d/m/Y').' '.substr($s->start_time, 0, 5).'–'.substr($s->end_time, 0, 5))
            ->all();

        $this->deliver($parent, new StudentPlacedNotification($enrollment, $upcoming));
    }

    private function notifyParents(ClassSession $session, string $kind): void
    {
        $this->parentsOf($session->classGroup)->each(fn (Collection $children) => $this->deliver(
            $children->first()->parent,
            new ClassSessionScheduleNotification($session, $kind, $children->pluck('name')->join(', '))
        ));
    }

    /** Siswa aktif kelas yang punya akun orang tua, dikelompokkan per orang tua. */
    private function parentsOf(ClassGroup $group): Collection
    {
        return Student::whereNotNull('parent_id')
            ->whereHas('enrollments', fn ($e) => $e->where('class_group_id', $group->id)->where('status', 'aktif'))
            ->with('parent')->get()->groupBy('parent_id');
    }

    private function deliver(User $recipient, Notification $notification): void
    {
        try {
            $recipient->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
