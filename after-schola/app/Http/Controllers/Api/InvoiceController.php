<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Branch;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modul 5 (bagian invoice): daftar, detail, batalkan.
 * Hak akses: permission "manage invoices" (dipasang di routes).
 */
class InvoiceController extends Controller
{
    private const WITH = ['registration.program', 'payments'];

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:unpaid,paid,void'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Invoice::inBranchesOf($request->user())->with(self::WITH);

        if ($request->filled('branch_id')) {
            $query->whereHas('registration', fn ($r) => $r->where('branch_id', $request->integer('branch_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(fn ($w) => $w
                ->where('number', 'like', $q)
                ->orWhereHas('registration', fn ($r) => $r->where('student_name', 'like', $q)->orWhere('parent_name', 'like', $q)));
        }

        // Opsi filter cabang ikut di respons: role Keuangan tidak punya akses ke /branches.
        return InvoiceResource::collection(
            $query->orderByDesc('issued_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        )->additional(['options' => [
            'branches' => Branch::visibleTo($request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]]);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('manage', $invoice);

        return new InvoiceResource($invoice->load(self::WITH));
    }

    /**
     * Kuitansi PDF (KEU-03) — hanya invoice lunas. Nomor kuitansi = nomor invoice.
     * TODO(nanti): format nomor kuitansi tersendiri bila diputuskan (OQ-14); unduhan oleh ortu menunggu portal.
     */
    public function receipt(Invoice $invoice)
    {
        $this->authorize('manage', $invoice);

        if ($invoice->status !== 'paid') {
            throw ValidationException::withMessages([
                'invoice' => ['Kuitansi hanya tersedia untuk invoice yang sudah lunas.'],
            ]);
        }

        $invoice->load('registration.program', 'registration.branch');
        $payment = $invoice->payments()->where('status', 'confirmed')->latest('confirmed_at')->first();

        return Pdf::loadView('exports.receipt', ['invoice' => $invoice, 'payment' => $payment])
            ->setPaper('a5', 'landscape')
            ->download('kuitansi-'.str_replace('/', '-', $invoice->number).'.pdf');
    }

    /**
     * Batalkan invoice; pendaftaran kembali ke antrian verifikasi agar bisa disetujui ulang (invoice baru).
     */
    public function void(Request $request, Invoice $invoice)
    {
        $this->authorize('manage', $invoice); // sebelum validasi: di luar cabang harus 404, bukan 422

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
}
