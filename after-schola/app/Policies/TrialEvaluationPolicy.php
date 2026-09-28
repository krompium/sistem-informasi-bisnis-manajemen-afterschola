<?php

namespace App\Policies;

use App\Models\TrialEvaluation;
use App\Models\User;

class TrialEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // difilter di controller: Management semua, trainer miliknya
    }

    public function view(User $user, TrialEvaluation $evaluation): bool
    {
        return $user->can('manage trial evaluations') || $evaluation->trainer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('submit trial evaluations') || $user->can('manage trial evaluations');
    }

    public function update(User $user, TrialEvaluation $evaluation): bool
    {
        return $user->can('manage trial evaluations') || $evaluation->trainer_id === $user->id;
    }

    public function delete(User $user, TrialEvaluation $evaluation): bool
    {
        return $user->can('manage trial evaluations') || $evaluation->trainer_id === $user->id;
    }
}
