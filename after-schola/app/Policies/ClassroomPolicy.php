<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Classroom $classroom): bool
    {
        return $user->handlesSchool($classroom->school_id);
    }

    public function create(User $user): bool
    {
        return $user->can('manage classrooms');
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->can('manage classrooms');
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->can('manage classrooms');
    }
}
