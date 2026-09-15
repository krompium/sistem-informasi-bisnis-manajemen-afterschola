<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

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
