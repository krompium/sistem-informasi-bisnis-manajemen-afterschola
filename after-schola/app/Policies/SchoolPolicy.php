<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // hasil difilter per role di controller
    }

    public function view(User $user, School $school): bool
    {
        return $user->handlesSchool($school->id);
    }

    public function create(User $user): bool
    {
        return $user->can('manage schools');
    }

    public function update(User $user, School $school): bool
    {
        return $user->can('manage schools');
    }

    public function delete(User $user, School $school): bool
    {
        return $user->can('manage schools');
    }

    public function assignTrainer(User $user, School $school): bool
    {
        return $user->can('manage schools');
    }
}
