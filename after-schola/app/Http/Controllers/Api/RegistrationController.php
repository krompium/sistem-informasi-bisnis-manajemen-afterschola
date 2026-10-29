<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Invoice;
use App\Models\PaymentSetting;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modul 5 (bagian verifikasi): antrian pendaftaran, setujui (+ invoice otomatis), minta revisi.
 * Hak akses: permission "verify registrations" (dipasang di routes).
 */
class RegistrationController extends Controller
{
    private const WITH = ['decision', 'program', 'activeInvoice'];

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:menunggu_verifikasi,perlu_revisi,terverifikasi'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Registration::query()->where('status', '!=', 'draft')->with(self::WITH);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(fn ($w) => $w->where('student_name', 'like', $q)->orWhere('parent_name', 'like', $q));
        }

        return RegistrationResource::collection(
            $query->orderByDesc('updated_at')->orderByDesc('id')->paginate($request->integer('per_page', 25))
        );
    }

    public function approve(Request $request, Registration $registration)
    {
        $this->ensureWaiting($registration);

        $program = $registration->program;
        if (! $program) {
            throw ValidationException::withMessages([
                'program' => ['Program pendaftaran tidak ditemukan, invoice tidak dapat diterbitkan.'],
            ]);
        }

        DB::transaction(function () use ($registration, $program, $request) {
            $registration->update([
                'status' => 'terverifikasi',
                'revision_note' => null,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ]);

            Invoice::create([
                'registration_id' => $registration->id,
                'number' => Invoice::nextNumber(),
                'amount' => $program->price, // snapshot harga saat terbit
                'due_date' => now()->addDays(PaymentSetting::current()->due_days)->toDateString(),
                'status' => 'unpaid',
                'issued_at' => now(),
            ]);
        });

        return new RegistrationResource($registration->fresh(self::WITH));
    }

    public function requestRevision(Request $request, Registration $registration)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);
        $this->ensureWaiting($registration);

        $registration->update([
            'status' => 'perlu_revisi',
            'revision_note' => $data['note'],
            'verified_by' => null,
            'verified_at' => null,
        ]);

        return new RegistrationResource($registration->fresh(self::WITH));
    }

    private function ensureWaiting(Registration $registration): void
    {
        if ($registration->status !== 'menunggu_verifikasi') {
            throw ValidationException::withMessages([
                'status' => ['Pendaftaran tidak sedang menunggu verifikasi.'],
            ]);
        }
    }
}
