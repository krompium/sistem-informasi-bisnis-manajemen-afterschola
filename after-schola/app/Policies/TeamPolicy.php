<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['management', 'developer']);
    }

    // Non-anggota mendapat 404 (bukan 403) supaya keberadaan team tidak bocor (konvensi §4.3).
    public function view(User $user, Team $team): Response|bool
    {
        if ($user->hasRole('management')) {
            return true;
        }

        return $this->isMember($user, $team) ? true : Response::denyAsNotFound();
    }

    // Q1: hanya Management yang membuat/mengubah/menghapus team dan anggotanya.
    public function create(User $user): bool
    {
        return $user->hasRole('management');
    }

    // Developer anggota: boleh lihat tapi tidak boleh tulis (403). Non-anggota: 404.
    public function update(User $user, Team $team): Response|bool
    {
        if ($user->hasRole('management')) {
            return true;
        }

        return $this->isMember($user, $team) ? false : Response::denyAsNotFound();
    }

    public function delete(User $user, Team $team): Response|bool
    {
        return $this->update($user, $team);
    }

    private function isMember(User $user, Team $team): bool
    {
        return $user->teams()->whereKey($team->id)->exists();
    }
}
