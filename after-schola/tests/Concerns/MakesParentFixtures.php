<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\EnrollmentDecision;
use App\Models\Lead;
use App\Models\Registration;
use App\Models\TrialEvaluation;
use App\Models\TrialSlot;
use App\Models\User;

/**
 * Fixture Portal Orang Tua: satu anak (lead) pada tahap tertentu dari alur trial sampai pembayaran.
 */
trait MakesParentFixtures
{
    use MakesFinanceFixtures;

    /** Urutan tahap; makeChild membangun semua langkah sampai tahap yang diminta. */
    private const STAGES = ['trial_terjadwal', 'hasil_trial', 'isi_pendaftaran', 'perlu_revisi', 'menunggu_verifikasi', 'menunggu_bayar', 'menunggu_konfirmasi', 'lunas'];

    protected function makeChild(User $parent, Branch $branch, string $stage = 'trial_terjadwal', string $name = 'Budi Santoso'): Lead
    {
        $program = $this->makeProgram(500000);
        $level = $program->levels()->create(['name' => 'Beginner', 'sort_order' => 1]);
        $slot = TrialSlot::create([
            'branch_id' => $branch->id, 'program_id' => $program->id, 'trainer_id' => $this->makeUser('trainer')->id,
            'starts_at' => now()->addDays(3), 'capacity' => 5,
        ]);
        $lead = Lead::create([
            'name' => $name, 'education_level' => 'sd', 'origin_school' => 'SDN 1', 'parent_email' => $parent->email,
            'parent_id' => $parent->id, 'branch_id' => $branch->id, 'program_id' => $program->id,
            'trial_slot_id' => $slot->id, 'status' => 'trial_scheduled',
        ]);

        $reached = fn (string $s) => array_search($stage, self::STAGES, true) >= array_search($s, self::STAGES, true);
        if (! $reached('hasil_trial')) {
            return $lead;
        }

        $evaluation = TrialEvaluation::create([
            'lead_id' => $lead->id, 'branch_id' => $branch->id, 'trial_slot_id' => $slot->id, 'program_id' => $program->id,
            'participant_name' => $name, 'trainer_id' => $slot->trainer_id, 'trial_date' => now()->toDateString(),
            'attendance' => 'hadir', 'score_enthusiasm' => 5, 'score_understanding' => 4, 'score_focus' => 3, 'score_collaboration' => 4,
            'strengths' => 'Cepat paham logika', 'improvements' => 'Perlu lebih fokus',
            'recommended_level_id' => $level->id, 'recommendation' => 'tidak',
        ]);
        if (! $reached('isi_pendaftaran')) {
            return $lead;
        }

        $decision = EnrollmentDecision::create(['evaluation_id' => $evaluation->id, 'decision' => 'lanjut', 'decided_at' => now()->toDateString(), 'decided_by' => $parent->id]);
        if ($stage === 'isi_pendaftaran') {
            return $lead;
        }

        $status = match (true) {
            $stage === 'perlu_revisi' => 'perlu_revisi',
            $stage === 'menunggu_verifikasi' => 'menunggu_verifikasi',
            default => 'terverifikasi',
        };
        $registration = Registration::create([
            'decision_id' => $decision->id, 'lead_id' => $lead->id, 'branch_id' => $branch->id, 'program_id' => $program->id,
            'student_name' => $name, 'level' => 'Beginner', 'parent_name' => $parent->name, 'parent_phone' => '081234567890',
            'status' => $status, 'revision_note' => $status === 'perlu_revisi' ? 'Nama sekolah belum lengkap' : null,
        ]);
        if (! $reached('menunggu_bayar')) {
            return $lead;
        }

        $invoice = $this->makeInvoice($registration, ['status' => $stage === 'lunas' ? 'paid' : 'unpaid', 'paid_at' => $stage === 'lunas' ? now() : null]);
        if ($stage !== 'menunggu_bayar') {
            $this->makePayment($invoice, $stage === 'lunas' ? ['status' => 'confirmed', 'confirmed_at' => now()] : []);
        }

        return $lead;
    }

    /** Evaluasi tidak hadir untuk lead yang sudah dijadwalkan. */
    protected function makeAbsentTrial(Lead $lead): void
    {
        TrialEvaluation::create([
            'lead_id' => $lead->id, 'branch_id' => $lead->branch_id, 'trial_slot_id' => $lead->trial_slot_id, 'program_id' => $lead->program_id,
            'participant_name' => $lead->name, 'trainer_id' => $lead->trialSlot->trainer_id, 'trial_date' => now()->toDateString(), 'attendance' => 'tidak_hadir',
        ]);
    }
}
