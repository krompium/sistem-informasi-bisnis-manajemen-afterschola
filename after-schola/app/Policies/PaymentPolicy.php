<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Pembayaran (Modul 5): permission "confirm payments" dicek di route; di sini cabangnya (BR-19).
 */
class PaymentPolicy
{
    public function manage(User $user, Payment $payment): Response
    {
        return $payment->isInBranchesOf($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
