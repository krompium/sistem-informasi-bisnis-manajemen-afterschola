<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Enrollment;
use App\Models\EnrollmentDecision;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Registration;
use App\Models\Student;
use App\Models\TrialEvaluation;
use App\Models\TrialSlot;
use App\Models\User;

/**
 * Fixture Portal Orang Tua. Dipakai bersama MakesBranchFixtures (makeStaff, makeBranch).
 */
trait MakesParentFixtures
{
    protected function makeParent(string $name = 'Ibu Budi'): User
    {
        $parent = $this->makeStaff('parent');
        $parent->update(['name' => $name, 'phone' => '081234567890']);

        return $parent;
    }

    protected function makeProgramWithLevel(string $name = 'Coding Kids', int $price = 500000): Program
    {
        $program = Program::create(['name' => $name, 'jenjang' => 'SD', 'tipe' => 'offline', 'price' => $price, 'is_active' => true]);
        $program->levels()->create(['name' => 'Dasar', 'sort_order' => 1]);

        return $program;
    }

    protected function makeSlot(Branch $branch, Program $program, array $overrides = []): TrialSlot
    {
        return TrialSlot::create(array_merge([
            'branch_id' => $branch->id,
            'program_id' => $program->id,
            'trainer_id' => $this->makeStaff('trainer', 'assigned', [$branch->id])->id,
            'starts_at' => now()->addDays(3),
            'capacity' => 10,
        ], $overrides));
    }

    /**
     * Anak (lead) milik $parent. $stage: scheduled (trial belum dievaluasi), evaluated (hadir), absent (tidak hadir).
     */
    protected function makeChild(User $parent, Branch $branch, Program $program, string $stage = 'evaluated', string $name = 'Budi'): Lead
    {
        $slot = $this->makeSlot($branch, $program, ['starts_at' => $stage === 'scheduled' ? now()->addDays(3) : now()->subDays(2)]);
        $lead = Lead::create([
            'name' => $name, 'education_level' => 'sd', 'origin_school' => 'SD Negeri 1',
            'parent_email' => $parent->email, 'parent_id' => $parent->id,
            'branch_id' => $branch->id, 'program_id' => $program->id, 'trial_slot_id' => $slot->id,
            'status' => 'trial_scheduled',
        ]);

        if ($stage !== 'scheduled') {
            $present = $stage === 'evaluated';
            TrialEvaluation::create([
                'lead_id' => $lead->id, 'branch_id' => $branch->id, 'trial_slot_id' => $slot->id, 'program_id' => $program->id,
                'participant_name' => $name, 'origin_school' => 'SD Negeri 1', 'trainer_id' => $slot->trainer_id,
                'trial_date' => today()->subDays(2), 'attendance' => $present ? 'hadir' : 'tidak_hadir',
                'score_enthusiasm' => $present ? 4 : null, 'score_understanding' => $present ? 5 : null,
                'score_focus' => $present ? 3 : null, 'score_collaboration' => $present ? 4 : null,
                'strengths' => $present ? 'Cepat memahami logika' : null, 'improvements' => $present ? 'Fokus perlu dilatih' : null,
                'recommended_level_id' => $present ? $program->levels()->first()->id : null,
                'recommendation' => $present ? 'lanjut' : null,
            ]);
        }

        return $lead->fresh();
    }

    protected function decideFor(Lead $lead, string $decision = 'lanjut'): EnrollmentDecision
    {
        $evaluation = $lead->trialEvaluation;
        if ($decision === 'tidak') {
            $lead->update(['status' => 'not_continued']);
        }

        return EnrollmentDecision::create(['evaluation_id' => $evaluation->id, 'decision' => $decision, 'decided_at' => today(), 'decided_by' => $lead->parent_id]);
    }

    protected function registrationFor(Lead $lead, string $status = 'draft'): Registration
    {
        $decision = $lead->trialEvaluation->decision ?? $this->decideFor($lead);

        return Registration::create([
            'decision_id' => $decision->id, 'lead_id' => $lead->id, 'branch_id' => $lead->branch_id, 'program_id' => $lead->program_id,
            'student_name' => $lead->name, 'origin_school' => $lead->origin_school, 'level' => 'Dasar',
            'parent_name' => $lead->parent->name, 'parent_phone' => '081234567890', 'parent_email' => $lead->parent->email,
            'status' => $status,
        ]);
    }

    protected function invoiceFor(Registration $registration, string $status = 'unpaid', array $overrides = []): Invoice
    {
        static $seq = 0;
        $seq++;

        return Invoice::create(array_merge([
            'registration_id' => $registration->id,
            'number' => sprintf('INV/%s/P%04d', now()->format('Ym'), $seq),
            'amount' => 500000,
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => $status,
            'issued_at' => now(),
            'paid_at' => $status === 'paid' ? now() : null,
        ], $overrides));
    }

    /** Siswa aktif di sebuah kelas, terhubung ke pendaftaran (hasil penempatan Modul 6). */
    protected function enrollStudent(Registration $registration, ?ClassGroup $classGroup = null): Enrollment
    {
        $lead = $registration->lead;
        $student = Student::create(['name' => $registration->student_name, 'branch_id' => $registration->branch_id, 'parent_id' => $lead->parent_id]);
        $registration->update(['student_id' => $student->id]);
        $classGroup ??= ClassGroup::create([
            'program_id' => $registration->program_id, 'level_id' => $registration->program->levels()->first()->id,
            'branch_id' => $registration->branch_id, 'name' => 'Coding A', 'capacity' => 10,
        ]);

        return Enrollment::create(['class_group_id' => $classGroup->id, 'student_id' => $student->id, 'start_date' => today(), 'status' => 'aktif']);
    }
}
