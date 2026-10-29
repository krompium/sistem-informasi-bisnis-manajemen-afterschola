<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Permission 'manage branches' dijaga di route; Policy ini menjaga scope cabang.
 */
class BranchPolicy
{
    public function view(User $user, Branch $branch): Response
    {
        return $user->handlesBranch($branch->id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Branch $branch): Response
    {
        return $this->view($user, $branch);
    }

    // Hanya pusat (scope 'all') yang boleh membuka cabang baru.
    public function create(User $user): bool
    {
        return $user->hasAllBranches();
    }
}
