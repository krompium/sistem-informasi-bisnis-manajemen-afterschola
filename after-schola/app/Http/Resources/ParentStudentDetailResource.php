<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Detail satu anak: hasil trial, penawaran, keputusan, pendaftaran, auto-fill, dan invoice aktif. Eager load tambahan: trialEvaluation.lead.parent, trialEvaluation.level, trialEvaluation.trainer. */
class ParentStudentDetailResource extends ParentStudentResource
{
    public function toArray(Request $request): array
    {
        $evaluation = $this->trialEvaluation;
        $attended = $evaluation?->attendance === 'hadir';
        $decision = $evaluation?->decision;
        $registration = $decision?->registration;
        $invoice = $registration?->activeInvoice;

        return parent::toArray($request) + [
            'trial_result' => $attended ? [
                'trial_date' => $evaluation->trial_date?->toDateString(),
                'trainer_name' => $evaluation->trainer?->name,
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
            'offer' => $attended && $this->program ? [
                'program_id' => $this->program->id,
                'name' => $this->program->name,
                'price' => (float) $this->program->price,
            ] : null,
            'prefill' => $attended ? $evaluation->registrationPrefill() : null,
            'decision' => $decision ? [
                'decision' => $decision->decision,
                'reason' => $decision->reason,
                'decided_at' => $decision->decided_at?->toDateString(),
            ] : null,
            'registration' => $registration ? [
                'status' => $registration->status,
                'program_id' => $registration->program_id,
                'revision_note' => $registration->revision_note,
                'student_name' => $registration->student_name,
                'birth_date' => $registration->birth_date?->toDateString(),
                'origin_school' => $registration->origin_school,
                'origin_class' => $registration->origin_class,
                'parent_name' => $registration->parent_name,
                'parent_phone' => $registration->parent_phone,
                'parent_email' => $registration->parent_email,
                'parent_address' => $registration->parent_address,
            ] : null,
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'amount' => (float) $invoice->amount,
                'due_date' => $invoice->due_date?->toDateString(),
                'status' => $invoice->status,
                'is_overdue' => $invoice->isOverdue(),
            ] : null,
        ];
    }
}
