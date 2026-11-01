<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Invoice dan pembayaran (Modul 5): permission dicek di route; di sini cabangnya (BR-19).
 * Pembayaran diotorisasi lewat invoice-nya.
 */
class InvoicePolicy
{
    public function manage(User $user, Invoice $invoice): Response
    {
        return $invoice->isInBranchesOf($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
