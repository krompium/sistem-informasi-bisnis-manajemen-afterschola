<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IntegrationListRequest;
use App\Http\Resources\IntegrationInvoiceResource;
use App\Http\Resources\IntegrationPaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * API integrasi read-only untuk Tim Keuangan (SYS-05, PRD bagian 10): invoice dan pembayaran.
 * Dibatasi cabang akun layanan (BR-19). Urut id naik supaya paginasi stabil; updated_since untuk sinkron inkremental.
 */
class IntegrationFinanceController extends Controller
{
    public function invoices(IntegrationListRequest $request)
    {
        $query = $this->filtered(Invoice::visibleTo($request->user()), $request, 'registration')
            ->with('registration.program:id,name', 'registration.branch:id,code')->orderBy('id');

        return IntegrationInvoiceResource::collection($query->paginate($request->integer('per_page', 25)));
    }

    public function payments(IntegrationListRequest $request)
    {
        $query = $this->filtered(Payment::whereHas('invoice', fn (Builder $i) => $i->visibleTo($request->user())), $request, 'invoice.registration')
            ->with('invoice.registration.branch:id,code')->orderBy('id');

        return IntegrationPaymentResource::collection($query->paginate($request->integer('per_page', 25)));
    }

    /** Filter yang sama untuk keduanya; $registration = jalur relasi ke pendaftaran. */
    private function filtered(Builder $query, IntegrationListRequest $request, string $registration): Builder
    {
        $filters = $request->validated();

        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['branch_id'] ?? null, fn (Builder $q, $id) => $q->whereHas($registration, fn (Builder $r) => $r->where('registrations.branch_id', $id)))
            ->when($filters['updated_since'] ?? null, fn (Builder $q, $since) => $q->where('updated_at', '>=', Carbon::parse($since)));
    }
}
