<?php

namespace App\Policies;

use App\Models\TrialEvaluation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Evaluasi trial (Modul 3, BR-19): staf evaluasi per cabang, trainer hanya evaluasi slot miliknya.
 * Di luar scope: 404 (tidak membocorkan keberadaan). Role tanpa permission evaluasi (mis. orang tua): 403.
 */
class TrialEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage trial evaluations') || $user->can('submit trial evaluations');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, TrialEvaluation $evaluation): Response
    {
        if ($user->can('manage trial evaluations')) {
            return $evaluation->isInBranchesOf($user) ? Response::allow() : Response::denyAsNotFound();
        }
        if ($user->can('submit trial evaluations')) {
            return $evaluation->trainer_id === $user->id ? Response::allow() : Response::denyAsNotFound();
        }

        return Response::deny();
    }

    public function update(User $user, TrialEvaluation $evaluation): Response
    {
        return $this->view($user, $evaluation);
    }

    public function delete(User $user, TrialEvaluation $evaluation): Response
    {
        return $this->view($user, $evaluation);
    }

    /** Keputusan dan pendaftaran resmi (Modul 4): permission "manage enrollment" dicek di route; di sini cabangnya. */
    public function enroll(User $user, TrialEvaluation $evaluation): Response
    {
        return $evaluation->isInBranchesOf($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
