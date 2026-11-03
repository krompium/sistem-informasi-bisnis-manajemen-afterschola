<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Student $student): bool
    {
        return $user->handlesSchool($student->school_id);
    }

    // Siswa reguler (Modul 7c): hanya yang berada di cabang user. Murid sekolah (tanpa cabang) bukan bagiannya: 404.
    public function viewRegular(User $user, Student $student): Response
    {
        return $student->branch_id !== null && $user->handlesBranch($student->branch_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Trainer boleh menambah murid di sekolah yang ia pegang; Management penuh.
     */
    public function create(User $user): bool
    {
        return $user->can('manage students') || $user->can('add students');
    }

    public function update(User $user, Student $student): bool
    {
        if ($user->can('manage students')) {
            return true;
        }

        // Trainer boleh mengubah murid di sekolah yang ia pegang.
        return $user->can('add students') && $user->handlesSchool($student->school_id);
    }

    public function delete(User $user, Student $student): bool
    {
        if ($user->can('manage students')) {
            return true;
        }

        return $user->can('add students') && $user->handlesSchool($student->school_id);
    }
}
