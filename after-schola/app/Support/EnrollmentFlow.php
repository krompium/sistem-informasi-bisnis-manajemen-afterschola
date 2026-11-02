<?php

namespace App\Support;

use App\Models\EnrollmentDecision;
use App\Models\Registration;
use App\Models\TrialEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan keputusan lanjut/tidak dan pendaftaran resmi (Modul 4). Dipakai staf (EnrollmentDecisionController)
 * dan orang tua (ParentChildController, ParentRegistrationController). Pelanggaran aturan dilempar sebagai
 * ValidationException (422). Otorisasi (siapa boleh menyentuh evaluasi ini) tetap di controller.
 */
class EnrollmentFlow
{
    public function ensureAttended(TrialEvaluation $evaluation): void
    {
        if ($evaluation->attendance !== 'hadir') {
            $this->reject('decision', 'Peserta tidak hadir saat trial, keputusan tidak dapat dicatat.');
        }
    }

    /**
     * Catat atau ubah keputusan (BR-02, BR-03). $data: decision, reason, decided_at, follow_up_date.
     */
    public function decide(TrialEvaluation $evaluation, array $data, User $by): void
    {
        $this->ensureAttended($evaluation);

        $registration = $evaluation->decision?->registration;

        // Pendaftaran yang sudah diajukan verifikasi tidak boleh dibatalkan lewat keputusan.
        if ($registration && ! in_array($registration->status, ['draft', 'perlu_revisi'], true) && $data['decision'] !== 'lanjut') {
            $this->reject('decision', 'Pendaftaran sudah diajukan untuk verifikasi, keputusan tidak dapat diubah.');
        }
        if ($data['decision'] !== 'pikir_pikir') {
            $data['follow_up_date'] = null;
        }
        $data['decided_at'] = $data['decided_at'] ?? now()->toDateString();
        $data['decided_by'] = $by->id;

        DB::transaction(function () use ($data, $registration, $evaluation) {
            // Draft pendaftaran tidak relevan lagi bila keputusan bukan "lanjut".
            if ($registration && $data['decision'] !== 'lanjut') {
                $registration->delete();
            }

            EnrollmentDecision::updateOrCreate(['evaluation_id' => $evaluation->id], $data);

            // BR-03: tidak lanjut → Leads (data tetap disimpan untuk remarketing). Keputusan lain mengembalikan ke calon.
            if ($lead = $evaluation->lead) {
                if ($data['decision'] === 'tidak') {
                    $lead->update(['status' => 'not_continued']);
                } elseif ($lead->status === 'not_continued') {
                    $lead->update(['status' => 'trial_scheduled']);
                }
            }
        });
    }

    /**
     * Pendaftaran yang boleh diisi: keputusan harus "lanjut" dan pendaftaran belum diajukan. Mengembalikan draft yang ada.
     */
    public function editableRegistration(TrialEvaluation $evaluation): ?Registration
    {
        $decision = $evaluation->decision;
        if (! $decision || $decision->decision !== 'lanjut') {
            $this->reject('decision', 'Pendaftaran hanya dapat diisi bila keputusan "lanjut".');
        }

        $existing = $decision->registration;
        if ($existing && ! in_array($existing->status, ['draft', 'perlu_revisi'], true)) {
            $this->reject('status', 'Pendaftaran sudah diajukan untuk verifikasi dan tidak dapat diubah.');
        }

        return $existing;
    }

    /**
     * Isi atau ubah form pendaftaran resmi. Identitas, cabang, dan level berasal dari server (evaluasi trial), bukan klien.
     */
    public function saveRegistration(TrialEvaluation $evaluation, array $data, User $by): Registration
    {
        $existing = $this->editableRegistration($evaluation);

        $data['program_id'] ??= $existing?->program_id ?? $evaluation->program_id;
        $data['lead_id'] = $evaluation->lead_id;
        $data['branch_id'] = $evaluation->branch_id;
        $data['level'] = $evaluation->level?->name ?? $existing?->level; // snapshot nama level rekomendasi
        $data['registered_by'] = $by->id;

        return Registration::updateOrCreate(['decision_id' => $evaluation->decision->id], $data);
    }

    /**
     * Ajukan pendaftaran untuk verifikasi (serah ke Modul 5). Setelah ini terkunci.
     */
    public function submitRegistration(TrialEvaluation $evaluation): void
    {
        $registration = $evaluation->decision?->registration;
        if (! $registration) {
            $this->reject('registration', 'Isi data pendaftaran terlebih dahulu.');
        }

        if (! in_array($registration->status, ['draft', 'perlu_revisi'], true)) {
            $this->reject('status', 'Pendaftaran sudah diajukan untuk verifikasi.');
        }

        $registration->update(['status' => 'menunggu_verifikasi']);
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
