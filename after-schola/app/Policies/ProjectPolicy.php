<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['management', 'developer']);
    }

    public function view(User $user, Project $project): Response|bool
    {
        if ($user->hasRole('management')) {
            return true;
        }

        return $this->memberOrNotFound($user, $project);
    }

    // Management read-only di proyek (PRD matriks akses).
    // Developer yang bukan anggota team proyek ditolak di FormRequest (422), bukan di sini.
    public function create(User $user): bool
    {
        return $user->hasRole('developer');
    }

    public function update(User $user, Project $project): Response|bool
    {
        if ($user->hasRole('management')) {
            return false;
        }

        return $this->memberOrNotFound($user, $project);
    }

    public function delete(User $user, Project $project): Response|bool
    {
        return $this->update($user, $project);
    }

    private function memberOrNotFound(User $user, Project $project): Response|bool
    {
        return $user->teams()->whereKey($project->team_id)->exists()
            ? true
            : Response::denyAsNotFound();
    }
}
