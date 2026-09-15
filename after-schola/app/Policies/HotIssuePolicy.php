<?php

namespace App\Policies;

use App\Models\HotIssue;
use App\Models\User;

class HotIssuePolicy
{
    /** Semua role yang login boleh lihat hot issue aktif (buat popup). */
    public function viewActive(User $user): bool
    {
        return true;
    }

    /** Kelola (create/update/delete) hanya untuk Management. */
    public function manage(User $user): bool
    {
        return $user->hasRole('Management');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, HotIssue $hotIssue): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, HotIssue $hotIssue): bool
    {
        return $this->manage($user);
    }
}