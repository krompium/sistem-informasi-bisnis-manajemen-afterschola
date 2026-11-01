<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu anak (lead) di portal orang tua + tahapnya. Status tampilan diturunkan dari lead, evaluasi trial,
 * keputusan, pendaftaran, dan invoice (tidak disimpan ganda). Tidak pernah memuat rekomendasi internal trainer.
 * Eager load: trialSlot.program, trialSlot.branch, program, trialEvaluation.decision.registration.activeInvoice.payments.
 */
class ParentStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slot = $this->trialSlot;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'education_level' => $this->education_level,
            'origin_school' => $this->origin_school,
            'stage' => $this->stage(),
            'program_name' => $slot?->program?->name ?? $this->program?->name,
            'branch_name' => $slot?->branch?->name,
            'trial_at' => $slot?->starts_at?->toIso8601String(),
        ];
    }

    /**
     * trial_terjadwal, tidak_hadir, hasil_trial, tidak_lanjut, isi_pendaftaran, perlu_revisi, menunggu_verifikasi,
     * menunggu_bayar, menunggu_konfirmasi, lunas.
     */
    protected function stage(): string
    {
        $evaluation = $this->trialEvaluation;

        if ($this->status === 'not_continued') {
            return 'tidak_lanjut';
        }
        if (! $evaluation) {
            return 'trial_terjadwal';
        }
        if ($evaluation->attendance !== 'hadir') {
            return 'tidak_hadir';
        }

        $decision = $evaluation->decision;
        if (! $decision || $decision->decision !== 'lanjut') {
            return 'hasil_trial'; // belum memutuskan (atau pikir-pikir)
        }

        $registration = $decision->registration;

        return match ($registration?->status) {
            null, 'draft' => 'isi_pendaftaran',
            'perlu_revisi' => 'perlu_revisi',
            'menunggu_verifikasi' => 'menunggu_verifikasi',
            default => $this->paymentStage($registration->activeInvoice),
        };
    }

    private function paymentStage(?Invoice $invoice): string
    {
        if ($invoice?->status === 'paid') {
            return 'lunas';
        }

        return $invoice?->payments->contains('status', 'pending') ? 'menunggu_konfirmasi' : 'menunggu_bayar';
    }
}
