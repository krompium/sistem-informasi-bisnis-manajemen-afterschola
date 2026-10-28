<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Aturan task = aturan proyek induknya (Management read-only, Developer hanya team-nya,
 * non-anggota 404), jadi didelegasikan ke ProjectPolicy.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['management', 'developer']);
    }

    public function view(User $user, Task $task): Response
    {
        return Gate::forUser($user)->inspect('view', $task->project);
    }

    // Developer yang bukan anggota team proyek ditolak di FormRequest (422), bukan di sini.
    public function create(User $user): bool
    {
        return $user->hasRole('developer');
    }

    public function update(User $user, Task $task): Response
    {
        return Gate::forUser($user)->inspect('update', $task->project);
    }

    public function delete(User $user, Task $task): Response
    {
        return $this->update($user, $task);
    }
}
