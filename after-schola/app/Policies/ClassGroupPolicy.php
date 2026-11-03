<?php

namespace App\Policies;

use App\Models\ClassGroup;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kelas (Modul 6a). Permission tulis dijaga di route; Policy ini menjaga siapa yang boleh
 * menyentuh kelas: staf per cabang, trainer per penugasan. Di luar scope: 404, bukan 403.
 */
class ClassGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('trainer') || $user->canAny(ClassGroup::STAFF_PERMISSIONS);
    }

    public function view(User $user, ClassGroup $classGroup): Response
    {
        return ClassGroup::visibleTo($user)->whereKey($classGroup->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
