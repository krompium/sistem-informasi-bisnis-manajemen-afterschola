<?php

namespace App\Policies;

use App\Models\ExpoReport;
use App\Models\User;

class ExpoReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // difilter: Management semua, trainer miliknya
    }

    public function view(User $user, ExpoReport $report): bool
    {
        return $user->can('manage expo reports') || $report->trainer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('submit expo reports') || $user->can('manage expo reports');
    }

    public function update(User $user, ExpoReport $report): bool
    {
        return $user->can('manage expo reports') || $report->trainer_id === $user->id;
    }

    public function delete(User $user, ExpoReport $report): bool
    {
        return $user->can('manage expo reports') || $report->trainer_id === $user->id;
    }
}
