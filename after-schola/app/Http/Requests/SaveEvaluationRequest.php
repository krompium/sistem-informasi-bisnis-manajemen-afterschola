<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use App\Models\RubricCriterion;
use App\Models\TrainerAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Buat evaluasi (enrollment + period) atau simpan draft (summary + scores). Skor divalidasi terhadap rubrik program kelas. */
class SaveEvaluationRequest extends FormRequest
{
    // Otorisasi sebelum validasi: di luar scope (termasuk ID yang tidak ada) 404, bukan 422.
    public function authorize(): bool
    {
        if ($evaluation = $this->route('evaluation')) {
            Gate::authorize('update', $evaluation);
        } elseif ($this->filled('enrollment_id')) {
            $enrollment = Enrollment::find($this->integer('enrollment_id'));
            abort_unless($enrollment && $this->isAssignedTrainer($enrollment), 404);
        }

        return true;
    }

    public function rules(): array
    {
        if (! ($evaluation = $this->route('evaluation'))) {
            return [
                'enrollment_id' => ['required', 'integer'],
                'period' => ['required', 'string', 'max:30', Rule::unique('evaluations', 'period')->where('enrollment_id', $this->integer('enrollment_id'))],
            ];
        }

        return [
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'scores' => ['sometimes', 'array'],
            'scores.*.criterion_id' => ['required', 'integer', 'distinct',
                Rule::exists('rubric_criteria', 'id')->where('program_id', $evaluation->enrollment->classGroup->program_id)],
            'scores.*.score' => ['bail', 'required', 'integer', 'min:0', function (string $attribute, mixed $value, \Closure $fail) {
                $max = RubricCriterion::whereKey($this->input('scores.'.explode('.', $attribute)[1].'.criterion_id'))->value('max_score');
                if ($max !== null && $value > $max) {
                    $fail("Skor tidak boleh melebihi {$max}.");
                }
            }],
        ];
    }

    // Hanya trainer yang ditugaskan di kelas siswa (utama/asisten) yang boleh mengevaluasi (BR-16), apa pun permission lainnya.
    private function isAssignedTrainer(Enrollment $enrollment): bool
    {
        return TrainerAssignment::where('class_group_id', $enrollment->class_group_id)
            ->where('trainer_id', $this->user()->id)->exists();
    }
}
