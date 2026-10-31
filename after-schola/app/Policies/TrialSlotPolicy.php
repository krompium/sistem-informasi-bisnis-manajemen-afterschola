<?php

namespace App\Policies;

use App\Models\TrialSlot;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Permission 'manage trial slots' dijaga di route; Policy ini menjaga scope cabang (404, bukan 403).
 */
class TrialSlotPolicy
{
    public function view(User $user, TrialSlot $slot): Response
    {
        return $user->handlesBranch($slot->branch_id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, TrialSlot $slot): Response
    {
        return $this->view($user, $slot);
    }

    public function delete(User $user, TrialSlot $slot): Response
    {
        return $this->view($user, $slot);
    }
}
