<?php

namespace App\Policies;

use App\Models\ClassSession;
use App\Models\User;

class ClassSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ClassSession $session): bool
    {
        return $user->handlesSchool($session->school_id);
    }

    // Jadwal dibuat Management; trainer read-only.
    public function create(User $user): bool
    {
        return $user->can('manage sessions');
    }

    public function update(User $user, ClassSession $session): bool
    {
        return $user->can('manage sessions');
    }

    public function delete(User $user, ClassSession $session): bool
    {
        return $user->can('manage sessions');
    }

    /**
     * Input/ubah absensi murid: trainer sekolah tsb atau Management,
     * dan pertemuan belum dikunci.
     */
    public function inputAttendance(User $user, ClassSession $session): bool
    {
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
    public function lock(User $user, ClassSession $session): bool
    {
        return $user->can('manage sessions')
            || ($session->trainer_id === $user->id && $user->handlesSchool($session->school_id));
    }

    /**
     * Trainer check-in dirinya di sesi ini.
     */
    public function trainerCheckin(User $user, ClassSession $session): bool
    {
        return $user->can('input trainer attendance') && $user->handlesSchool($session->school_id);
    }
}
