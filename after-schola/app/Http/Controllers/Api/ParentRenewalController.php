<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParentInvoiceResource;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Registration;
use App\Support\InvoiceIssuer;
use App\Support\ParentNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Portal Orang Tua (ORT-12, BR-18, A-05, AC-15): ajukan perpanjangan untuk anak yang sedang aktif.
 * Registrasi perpanjangan langsung terverifikasi (tanpa verifikasi ulang) dan invoice baru terbit.
 * Enrollment berjalan tidak disentuh. Role "parent" dijaga middleware route; anak orang lain = 404.
 * TODO(nanti): PRD belum menjelaskan penempatan setelah invoice perpanjangan lunas; daftar kandidat penempatan
 * akan memuat registrasi lama dan perpanjangan sekaligus begitu enrollment lama berakhir.
 */
class ParentRenewalController extends Controller
{
    public function __invoke(Request $request, int $child, InvoiceIssuer $issuer, ParentNotifier $notifier)
    {
        $parent = $request->user();
        $lead = Lead::where('parent_id', $parent->id)->findOrFail($child);

        $invoice = DB::transaction(function () use ($lead, $parent, $issuer) {
            // Kunci baris anak: dua klik bersamaan tidak membuat dua perpanjangan.
            Lead::whereKey($lead->id)->lockForUpdate()->first();

            // Dasar perpanjangan: pendaftaran terbaru anak ini yang sudah punya siswa (hasil penempatan).
            $base = Registration::where('lead_id', $lead->id)->whereNotNull('student_id')->with('program', 'branch')->latest('id')->first();
            if (! $base || ! Enrollment::where('student_id', $base->student_id)->where('status', 'aktif')->exists()) {
                throw ValidationException::withMessages(['renewal' => ['Perpanjangan hanya untuk anak yang sedang aktif di sebuah kelas.']]);
            }
            if (! $base->program) {
                throw ValidationException::withMessages(['renewal' => ['Program tidak ditemukan, invoice tidak dapat diterbitkan.']]);
            }
            $hasUnpaid = Invoice::where('status', 'unpaid')
                ->whereHas('registration', fn ($registration) => $registration->where('lead_id', $lead->id)->where('type', 'perpanjangan'))
                ->exists();
            if ($hasUnpaid) {
                throw ValidationException::withMessages(['renewal' => ['Masih ada invoice perpanjangan yang belum dibayar.']]);
            }

            $renewal = Registration::create($base->only([
                'lead_id', 'branch_id', 'program_id', 'student_id', 'student_name', 'birth_date', 'origin_school', 'origin_class', 'level',
                'parent_name', 'parent_phone', 'parent_email', 'parent_address',
            ]) + ['type' => 'perpanjangan', 'status' => 'terverifikasi', 'registered_by' => $parent->id]);

            return $issuer->issue($renewal, $base->program);
        });

        $amount = 'Rp '.number_format((float) $invoice->amount, 0, ',', '.');
        $notifier->notify($parent, 'renewal_invoice', 'Invoice perpanjangan terbit',
            "Invoice perpanjangan {$invoice->number} sebesar {$amount} jatuh tempo {$invoice->due_date->format('d/m/Y')}.",
            ['lead_id' => $lead->id, 'invoice_id' => $invoice->id]);

        return (new ParentInvoiceResource($invoice->load('registration.program', 'payments')))->response()->setStatusCode(201);
    }
}
