<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Models\Program;
use App\Models\Registration;

/**
 * Menerbitkan invoice unpaid untuk sebuah registrasi (BR-06): nominal = harga program saat terbit (snapshot),
 * jatuh tempo = default Management, nomor unik per cabang dan bulan.
 * Dipakai verifikasi pendaftaran dan perpanjangan. Panggil di dalam transaksi pemanggil (nomor memakai lockForUpdate).
 */
class InvoiceIssuer
{
    public function issue(Registration $registration, Program $program): Invoice
    {
        return Invoice::create([
            'registration_id' => $registration->id,
            'number' => Invoice::nextNumber($registration->branch?->code),
            'amount' => $program->price,
            'due_date' => now()->addDays(PaymentSetting::current()->due_days)->toDateString(),
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);
    }
}
