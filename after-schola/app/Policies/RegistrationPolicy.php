<?php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Verifikasi pendaftaran (Modul 5): permission "verify registrations" dicek di route; di sini cabangnya (BR-19).
 */
class RegistrationPolicy
{
    public function verify(User $user, Registration $registration): Response
    {
        return $registration->isInBranchesOf($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
