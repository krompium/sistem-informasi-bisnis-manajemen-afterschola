<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Models\TrialEvaluation;
use App\Models\TrialSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveTrialEvaluationRequest extends FormRequest
{
    // Otorisasi sebelum validasi: evaluasi di luar scope harus 404, bukan 422.
    public function authorize(): bool
    {
        $evaluation = $this->route('trialEvaluation');
        $evaluation ? Gate::authorize('update', $evaluation) : Gate::authorize('create', TrialEvaluation::class);

        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        // Skor & rekomendasi wajib hanya bila peserta hadir (saat membuat baru).
        $ifPresent = $creating ? ['required_if:attendance,hadir', 'nullable'] : ['nullable'];
        $score = [...$ifPresent, 'integer', 'between:1,5'];

        $rules = [
            'attendance' => [$required, 'in:hadir,tidak_hadir'],
            'score_enthusiasm' => $score,
            'score_understanding' => $score,
            'score_focus' => $score,
            'score_collaboration' => $score,
            'strengths' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
            'recommended_level_id' => [
                'nullable', 'integer',
                Rule::exists('program_levels', 'id')->where('program_id', $this->programId()),
            ],
            'recommendation' => [...$ifPresent, 'in:lanjut,ragu,tidak'],
        ];

        if ($creating) {
            // Peserta = lead yang memesan slot yang boleh dinilai user ini (FK dari body, jadi divalidasi di sini).
            $rules['lead_id'] = [
                'bail', 'required', 'integer',
                Rule::exists('leads', 'id')->where(
                    fn ($q) => $q->whereIn('trial_slot_id', TrialSlot::evaluableBy($this->user())->select('trial_slots.id')),
                ),
                Rule::unique('trial_evaluations', 'lead_id'),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'lead_id.exists' => 'Peserta tidak ditemukan pada slot trial Anda.',
            'lead_id.unique' => 'Peserta ini sudah dievaluasi.',
            'recommended_level_id.exists' => 'Level tidak termasuk dalam program trial ini.',
        ];
    }

    // Level harus milik program trial: dari evaluasi yang diubah, atau dari slot peserta saat membuat baru.
    private function programId(): ?int
    {
        if ($evaluation = $this->route('trialEvaluation')) {
            return $evaluation->program_id;
        }

        return Lead::with('trialSlot:id,program_id')->find($this->integer('lead_id'))?->trialSlot?->program_id;
    }
}
