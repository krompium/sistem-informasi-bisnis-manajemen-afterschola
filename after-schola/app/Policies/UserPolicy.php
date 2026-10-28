<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Permission 'manage users' dijaga di route; Policy ini menjaga scope cabang.
 * Aturan visibilitas satu sumber: User::scopeVisibleTo().
 */
class UserPolicy
{
    public function view(User $actor, User $target): Response
    {
        return User::visibleTo($actor)->whereKey($target->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $actor, User $target): Response
    {
        return $this->view($actor, $target);
    }

    public function create(User $actor): bool
    {
        return true;
    }
}
