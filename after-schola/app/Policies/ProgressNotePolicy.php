<?php

namespace App\Policies;

use App\Models\ProgressNote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Di luar scope: 404, bukan 403 (PRD §9). Mengubah hanya boleh oleh penulis. Hak tulis (role trainer) dijaga di route. */
class ProgressNotePolicy
{
    public function view(User $user, ProgressNote $note): Response
    {
        return ProgressNote::visibleTo($user)->whereKey($note->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, ProgressNote $note): Response
    {
        $visible = $this->view($user, $note);

        if ($visible->denied()) {
            return $visible;
        }

        return $note->trainer_id === $user->id
            ? Response::allow()
            : Response::deny('Hanya penulis catatan yang dapat mengubahnya.');
    }
}
