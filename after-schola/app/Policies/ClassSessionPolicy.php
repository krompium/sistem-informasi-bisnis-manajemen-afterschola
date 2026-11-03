<?php

namespace App\Policies;

use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\TrainerAssignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClassSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ClassSession $session): bool|Response
    {
        if ($this->isRegular($session)) {
            return Response::denyAsNotFound();
        }

        return $user->handlesSchool($session->school_id);
    }

    // Sesi kelas reguler (Modul 6c): scope cabang/penugasan; di luar scope 404. Permission tulis (manage schedule) di route.
    public function viewRegular(User $user, ClassSession $session): Response
    {
        return ClassSession::visibleToRegular($user)->whereKey($session->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    // Jadwal dibuat Management; trainer read-only.
    public function create(User $user): bool
    {
        return $user->can('manage sessions');
    }

    public function update(User $user, ClassSession $session): bool|Response
    {
        if ($this->isRegular($session)) {
            return Response::denyAsNotFound();
        }

        return $user->can('manage sessions');
    }

    public function delete(User $user, ClassSession $session): bool|Response
    {
        if ($this->isRegular($session)) {
            return Response::denyAsNotFound();
        }

        return $user->can('manage sessions');
    }

    /**
     * Input/ubah absensi murid: trainer sekolah tsb atau Management,
     * dan pertemuan belum dikunci.
     */
    public function inputAttendance(User $user, ClassSession $session): bool|Response
    {
        if ($this->isRegular($session)) {
            return Response::denyAsNotFound();
        }

        if ($session->is_locked) {
            return false;
        }

        if ($user->can('input student attendance') && $user->handlesSchool($session->school_id)) {
            return true;
        }

        return $user->can('manage sessions');
    }

    /**
     * Kunci/buka pertemuan: Management atau trainer pengampu sesi.
     */
    public function lock(User $user, ClassSession $session): bool|Response
    {
        if ($this->isRegular($session)) {
            return Response::denyAsNotFound();
        }

        return $user->can('manage sessions')
            || ($session->trainer_id === $user->id && $user->handlesSchool($session->school_id));
    }

    /**
     * Trainer check-in dirinya di sesi ini.
     */
    public function trainerCheckin(User $user, ClassSession $session): bool|Response
    {
        if ($this->isRegular($session)) {
            return Response::denyAsNotFound();
        }

        return $user->can('input trainer attendance') && $user->handlesSchool($session->school_id);
    }

    // Absensi sekolah (/sessions) tidak melayani sesi kelas reguler; sesi reguler hanya lewat /class-sessions.
    private function isRegular(ClassSession $session): bool
    {
        return $session->class_group_id !== null;
    }

    // Absensi siswa reguler (Modul 7a): trainer pelaksana atau yang ditugaskan di kelas; Management hanya baca.
    public function inputRegularAttendance(User $user, ClassSession $session): Response
    {
        return $this->regularDenial($user, $session)
            ?? ($this->teaches($user, $session) ? Response::allow() : Response::deny());
    }

    // Kunci sesi: pelaksana, trainer yang ditugaskan, atau pemegang "manage schedule".
    public function lockRegular(User $user, ClassSession $session): Response
    {
        return $this->regularDenial($user, $session)
            ?? ($this->teaches($user, $session) || $user->can('manage schedule') ? Response::allow() : Response::deny());
    }

    // Buka kunci sesi (koreksi): hanya pemegang "manage schedule".
    public function unlockRegular(User $user, ClassSession $session): Response
    {
        return $this->regularDenial($user, $session)
            ?? ($user->can('manage schedule') ? Response::allow() : Response::deny());
    }

    // Peran tanpa akses kelas (mis. orang tua): 403. Sesi di luar scope user: 404.
    private function regularDenial(User $user, ClassSession $session): ?Response
    {
        if (! $user->hasRole('trainer') && ! $user->canAny(ClassGroup::STAFF_PERMISSIONS)) {
            return Response::deny();
        }

        $visible = $this->viewRegular($user, $session);

        return $visible->denied() ? $visible : null;
    }

    private function teaches(User $user, ClassSession $session): bool
    {
        return $session->trainer_id === $user->id
            || TrainerAssignment::where('class_group_id', $session->class_group_id)->where('trainer_id', $user->id)->exists();
    }
}
