<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Permission 'manage leads' dijaga di route; Policy ini menjaga scope cabang (404, bukan 403).
 * Lead lama tanpa cabang (data demo) hanya boleh diakses scope 'all'.
 */
class LeadPolicy
{
    public function update(User $user, Lead $lead): Response
    {
        $allowed = $lead->branch_id === null ? $user->hasAllBranches() : $user->handlesBranch($lead->branch_id);

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
