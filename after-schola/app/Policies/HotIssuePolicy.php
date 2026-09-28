<?php

namespace App\Policies;

use App\Models\HotIssue;
use App\Models\User;

class HotIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // semua role login boleh melihat hot issue aktif miliknya
    }

    public function view(User $user, HotIssue $hotIssue): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('manage hot issues');
    }

    public function update(User $user, HotIssue $hotIssue): bool
    {
        return $user->can('manage hot issues');
    }

    public function delete(User $user, HotIssue $hotIssue): bool
    {
        return $user->can('manage hot issues');
    }

    // Menutup popup untuk diri sendiri — semua role boleh.
    public function dismiss(User $user, HotIssue $hotIssue): bool
    {
        return true;
    }
}
