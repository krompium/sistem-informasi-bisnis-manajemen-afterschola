<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\TrialSlot;
use App\Models\User;
use App\Notifications\TrialRegisteredNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    // MGT-21/22 (permission: manage leads, scope cabang): daftar calon dan leads untuk staf.
    public function index(Request $request)
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(Lead::STATUS_LABELS))],
        ]);

        $leads = Lead::visibleTo($request->user())->filter($filters)
            ->with(['parent:id,name,email,phone', 'branch:id,name', 'program:id,name', 'trialSlot.program:id,name', 'trialSlot.branch:id,name'])
            ->latest('id')
            ->paginate(25);

        return LeadResource::collection($leads);
    }

    // Staf hanya mengubah catatan (MGT-22).
    public function update(UpdateLeadRequest $request, Lead $lead)
    {
        $lead->update($request->validated());

        return new LeadResource($lead->load(['parent:id,name,email,phone', 'branch:id,name', 'program:id,name', 'trialSlot.program:id,name', 'trialSlot.branch:id,name']));
    }

    // Form trial publik (PUB-03..05): memesan slot, membuat akun Orangtua dan lead dalam satu transaksi.
    public function store(StoreLeadRequest $request)
    {
        $data = $request->validated();

        [$lead, $parent] = DB::transaction(function () use ($data) {
            $slot = $this->lockBookableSlot((int) $data['trial_slot_id']);

            $parent = User::create([
                'name' => $data['parent_name'],
                'email' => $data['parent_email'],
                'phone' => $data['parent_phone'],
                'password' => Str::random(40), // orang tua mengatur sendiri lewat tautan di email
                'is_active' => true,
            ]);
            $parent->assignRole('parent');

            $lead = Lead::create([
                'name' => $data['name'],
                'education_level' => $data['education_level'],
                'origin_school' => $data['origin_school'],
                'parent_email' => $data['parent_email'], // TODO(nanti): hapus kolom ini, sumber tunggal users.email (portal orang tua)
                'parent_id' => $parent->id,
                'branch_id' => $slot->branch_id,
                'program_id' => $slot->program_id,
                'trial_slot_id' => $slot->id,
                'status' => 'trial_scheduled',
                'consented_at' => now(),
            ]);

            return [$lead, $parent];
        });

        $this->sendConfirmation($lead, $parent);

        return (new LeadResource($lead->load('trialSlot.program', 'trialSlot.branch')))->response()->setStatusCode(201);
    }

    // Booking diserialkan per slot: kunci baris slot lebih dulu, baru cek ulang kelayakan dan sisa kursi
    // (pembacaan kedua ini harus yang pertama bersifat non-locking, supaya melihat booking yang baru di-commit).
    private function lockBookableSlot(int $id): TrialSlot
    {
        TrialSlot::whereKey($id)->lockForUpdate()->first();

        $slot = TrialSlot::bookable()->find($id);
        if (! $slot) {
            throw ValidationException::withMessages(['trial_slot_id' => ['Slot trial tidak tersedia atau sudah penuh.']]);
        }

        return $slot;
    }

    // Kegagalan kirim tidak membatalkan pendaftaran: dicatat (NFR-07), notifikasi in-app tetap tersimpan.
    // TODO(nanti): antrekan (ShouldQueue) bila pengiriman email memperlambat respons form.
    private function sendConfirmation(Lead $lead, User $parent): void
    {
        try {
            $parent->notify(new TrialRegisteredNotification($lead, Password::createToken($parent)));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim konfirmasi pendaftaran trial.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }
    }
}
