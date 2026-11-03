<?php

namespace App\Http\Requests;

use App\Models\ClassGroup;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveTrainerAssignmentRequest extends FormRequest
{
    // Otorisasi sebelum validasi: kelas di luar scope user harus 404, bukan 422.
    public function authorize(): bool
    {
        $group = $this->route('trainer_assignment')?->classGroup
            ?? ClassGroup::find($this->input('class_group_id'));

        if ($group) {
            Gate::authorize('view', $group);
        }

        return true;
    }

    public function rules(): array
    {
        $rules = ['role' => ['required', Rule::in(['utama', 'asisten'])]];

        // Mengubah penugasan hanya mengubah peran; kelas dan trainer tidak dipindah.
        if ($this->isMethod('POST')) {
            $rules['class_group_id'] = ['bail', 'required', 'integer', 'exists:class_groups,id'];
            $rules['trainer_id'] = [
                'bail', 'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $trainer = User::role('trainer')->where('is_active', true)->find($value);
                    $group = ClassGroup::find($this->input('class_group_id'));
                    if (! $trainer) {
                        $fail('Trainer tidak valid.');
                    } elseif ($group && ! $trainer->handlesBranch($group->branch_id)) {
                        $fail('Trainer tidak ditugaskan di cabang kelas ini.');
                    }
                },
                Rule::unique('trainer_assignments', 'trainer_id')->where('class_group_id', $this->input('class_group_id')),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'trainer_id.unique' => 'Trainer sudah ditugaskan di kelas ini.',
            'role.in' => 'Peran harus utama atau asisten.',
        ];
    }
}
