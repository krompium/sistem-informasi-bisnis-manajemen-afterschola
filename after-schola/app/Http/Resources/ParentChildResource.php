<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Anak dalam Portal Orang Tua: satu entitas dari trial sampai aktif (BR-01). Sebelum penempatan wujudnya Lead;
 * status tampilan (stage) diturunkan dari evaluasi, keputusan, pendaftaran, invoice, dan enrollment aktif.
 *
 * @mixin Lead
 */
class ParentChildResource extends JsonResource
{
    private const STAGE_LABELS = [
        'trial_terjadwal' => 'Trial terjadwal',
        'menunggu_keputusan' => 'Hasil trial siap, menunggu keputusan',
        'tidak_hadir' => 'Tidak hadir saat trial',
        'tidak_lanjut' => 'Tidak melanjutkan',
        'siap_daftar' => 'Siap mendaftar',
        'perlu_revisi' => 'Data pendaftaran perlu diperbaiki',
        'menunggu_verifikasi' => 'Menunggu verifikasi pendaftaran',
        'menunggu_bayar' => 'Menunggu pembayaran',
        'siap_dijadwalkan' => 'Menunggu penempatan kelas',
        'aktif' => 'Aktif belajar',
    ];

    public function toArray(Request $request): array
    {
        $evaluation = $this->trialEvaluation;
        $decision = $evaluation?->decision;
        $registration = $decision?->registration;
        [$stage, $nextAction] = $this->stage();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'education_level' => $this->education_level,
            'origin_school' => $this->origin_school,
            'program' => $this->program ? ['id' => $this->program->id, 'name' => $this->program->name, 'price' => (float) $this->program->price] : null,
            'branch' => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null,
            'trial_starts_at' => $this->trialSlot?->starts_at?->toIso8601String(),
            'stage' => $stage,
            'stage_label' => self::STAGE_LABELS[$stage],
            'next_action' => $nextAction,
            'evaluation' => $evaluation ? [
                'attendance' => $evaluation->attendance,
                'trial_date' => $evaluation->trial_date?->toDateString(),
                'scores' => [
                    'enthusiasm' => $evaluation->score_enthusiasm,
                    'understanding' => $evaluation->score_understanding,
                    'focus' => $evaluation->score_focus,
                    'collaboration' => $evaluation->score_collaboration,
                ],
                'strengths' => $evaluation->strengths,
                'improvements' => $evaluation->improvements,
                'recommended_level' => $evaluation->level?->name,
            ] : null,
            'decision' => $decision ? [
                'decision' => $decision->decision,
                'reason' => $decision->reason,
                'decided_at' => $decision->decided_at?->toDateString(),
            ] : null,
            'registration' => $registration ? [
                'id' => $registration->id,
                'status' => $registration->status,
                'revision_note' => $registration->revision_note,
            ] : null,
        ];
    }

    /** @return array{0: string, 1: ?string} [stage, tindakan yang menunggu orang tua] */
    private function stage(): array
    {
        $evaluation = $this->trialEvaluation;
        $decision = $evaluation?->decision;
        $registration = $decision?->registration;

        if ($registration) {
            if ($registration->student?->enrollments->contains('status', 'aktif')) {
                return ['aktif', null];
            }

            return match ($registration->status) {
                'draft' => ['siap_daftar', 'daftar'],
                'perlu_revisi' => ['perlu_revisi', 'perbaiki_data'],
                'menunggu_verifikasi' => ['menunggu_verifikasi', null],
                default => match ($registration->activeInvoice?->status) {
                    'paid' => ['siap_dijadwalkan', null],
                    'unpaid' => ['menunggu_bayar', 'bayar'],
                    default => ['menunggu_bayar', null], // belum ada invoice aktif (mis. di-void), Keuangan menerbitkan ulang
                },
            };
        }

        if (! $evaluation) {
            return ['trial_terjadwal', null];
        }
        if ($evaluation->attendance !== 'hadir') {
            return ['tidak_hadir', null];
        }

        return match ($decision?->decision) {
            'lanjut' => ['siap_daftar', 'daftar'],
            'tidak' => ['tidak_lanjut', null],
            default => ['menunggu_keputusan', 'lihat_hasil'], // belum diputuskan, atau pikir_pikir dari staf
        };
    }
}
