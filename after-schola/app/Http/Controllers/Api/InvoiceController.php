<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modul 5 (bagian invoice): daftar, detail, batalkan, kuitansi.
 * Hak akses: permission "manage invoices" (dipasang di routes).
 */
class InvoiceController extends Controller
{
    private const WITH = ['registration.program', 'payments'];

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:unpaid,paid,void'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Invoice::visibleTo($request->user())->with(self::WITH);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(fn ($w) => $w
                ->where('number', 'like', $q)
                ->orWhereHas('registration', fn ($r) => $r->where('student_name', 'like', $q)->orWhere('parent_name', 'like', $q)));
        }

        return InvoiceResource::collection(
            $query->orderByDesc('issued_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice->load(self::WITH));
    }

    /**
     * Batalkan invoice; pendaftaran kembali ke antrian verifikasi agar bisa disetujui ulang (invoice baru).
     */
    public function void(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice); // sebelum validasi: di luar cabang harus 404, bukan 422
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($invoice->status !== 'unpaid') {
            throw ValidationException::withMessages([
                'status' => ['Hanya invoice yang belum lunas yang dapat dibatalkan.'],
            ]);
        }
        if ($invoice->payments()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            throw ValidationException::withMessages([
                'invoice' => ['Tolak dulu pembayaran yang menunggu konfirmasi sebelum membatalkan invoice.'],
            ]);
        }

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update(['status' => 'void', 'void_reason' => $data['reason']]);
            $invoice->registration->update([
                'status' => 'menunggu_verifikasi',
                'verified_by' => null,
                'verified_at' => null,
            ]);
        });

        return new InvoiceResource($invoice->fresh(self::WITH));
    }

    /**
     * Kuitansi PDF (KEU-03): hanya untuk invoice Lunas.
     * TODO(nanti): terbilang dan nomor kuitansi tersendiri bila Keuangan memintanya (OQ-14).
     */
    public function receipt(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        if ($invoice->status !== 'paid') {
            throw ValidationException::withMessages(['status' => ['Kuitansi hanya tersedia untuk invoice yang sudah lunas.']]);
        }

        $invoice->load('registration.program', 'registration.branch');
        $pdf = Pdf::loadView('exports.receipt', [
            'invoice' => $invoice,
            'registration' => $invoice->registration,
            'branch' => $invoice->registration->branch,
            'payment' => $invoice->payments()->where('status', 'confirmed')->latest('id')->first(),
        ])->setPaper('a5', 'landscape');

        return $pdf->download('kuitansi-'.str_replace('/', '-', $invoice->number).'.pdf');
    }
}
