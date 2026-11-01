<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Invoice dan pembayaran (Modul 5): permission dicek di route (staf); di sini cabangnya (BR-19).
 * Pembayaran diotorisasi lewat invoice-nya.
 */
class InvoicePolicy
{
    public function manage(User $user, Invoice $invoice): Response
    {
        // Portal Orang Tua: ortu hanya invoice anaknya sendiri, tanpa scope cabang.
        if ($user->hasRole('parent')) {
            return $invoice->parentUser()?->id === $user->id ? Response::allow() : Response::denyAsNotFound();
        }

        return $invoice->isInBranchesOf($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
