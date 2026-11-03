<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Invoice dan payment (Modul 5): permission dicek di route; di sini cabangnya (BR-19).
 * Aksi atas payment memakai invoice induknya.
 */
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): Response
    {
        return $invoice->isInBranchesOf($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
