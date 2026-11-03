<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Di luar scope: 404 (PRD §9). Hak tulis (role trainer) dijaga di route; mengubah dan mengajukan hanya oleh penulis. */
class EvaluationPolicy
{
    public function view(User $user, Evaluation $evaluation): Response
    {
        return Evaluation::visibleTo($user)->whereKey($evaluation->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Evaluation $evaluation): Response
    {
        $visible = $this->view($user, $evaluation);

        if ($visible->denied()) {
            return $visible;
        }

        return $evaluation->evaluator_id === $user->id
            ? Response::allow()
            : Response::deny('Hanya penulis evaluasi yang dapat mengubahnya.');
    }
}
