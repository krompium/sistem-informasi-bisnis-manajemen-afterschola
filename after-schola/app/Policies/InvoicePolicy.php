<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Invoice (Modul 5): permission "manage invoices" dicek di route; di sini cabangnya (BR-19).
 * Di luar cabang: 404, bukan 403, agar keberadaan invoice tidak bocor.
 */
class InvoicePolicy
{
    public function manage(User $user, Invoice $invoice): Response
    {
        return $invoice->isInBranchesOf($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
