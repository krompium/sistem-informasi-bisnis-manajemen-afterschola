<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrialEvaluationResource;
use App\Models\TrialEvaluation;
use Illuminate\Http\Request;

class TrialEvaluationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', TrialEvaluation::class);
        $user = $request->user();

        $query = TrialEvaluation::query()->with('trainer');

        // Management melihat semua; trainer hanya evaluasi miliknya.
        if (! $user->can('manage trial evaluations')) {
            $query->where('trainer_id', $user->id);
        }
        if ($request->filled('q')) {
            $query->where('participant_name', 'like', '%'.$request->string('q').'%');
        }
        if ($request->filled('recommendation')) {
            $query->where('recommendation', $request->string('recommendation'));
        }
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->integer('lead_id'));
        }

        return TrialEvaluationResource::collection(
            $query->orderByDesc('trial_date')->orderByDesc('id')->get()
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', TrialEvaluation::class);

        $data = $this->normalize($request->validate($this->rules()));
        $data['trainer_id'] = $request->user()->id;

        $evaluation = TrialEvaluation::create($data);

        return (new TrialEvaluationResource($evaluation->load('trainer')))->response()->setStatusCode(201);
    }

    public function show(TrialEvaluation $trialEvaluation)
    {
        $this->authorize('view', $trialEvaluation);

        return new TrialEvaluationResource($trialEvaluation->load('trainer'));
    }

    public function update(Request $request, TrialEvaluation $trialEvaluation)
    {
        $this->authorize('update', $trialEvaluation);

        $data = $this->normalize($request->validate($this->rules(partial: true)));
        $trialEvaluation->update($data);

        return new TrialEvaluationResource($trialEvaluation->load('trainer'));
    }

    public function destroy(TrialEvaluation $trialEvaluation)
    {
        $this->authorize('delete', $trialEvaluation);

        $trialEvaluation->delete();

        return response()->json(['message' => 'Evaluasi trial dihapus.']);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        // Skor & rekomendasi wajib hanya bila peserta hadir (saat membuat baru).
        $ifPresent = $partial ? ['nullable'] : ['required_if:attendance,hadir', 'nullable'];
        $score = [...$ifPresent, 'integer', 'between:1,5'];

        return [
            'lead_id' => ['nullable', 'integer', 'min:1'],
            'program_id' => ['nullable', 'integer', 'min:1'],
            'participant_name' => [$required, 'string', 'max:255'],
            'origin_school' => ['nullable', 'string', 'max:255'],
            'origin_class' => ['nullable', 'string', 'max:50'],
            'trial_date' => [$required, 'date'],
            'attendance' => [$required, 'in:hadir,tidak_hadir'],
            'score_enthusiasm' => $score,
            'score_understanding' => $score,
            'score_focus' => $score,
            'score_collaboration' => $score,
            'strengths' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
            'recommended_level' => ['nullable', 'in:beginner,intermediate'],
            'recommendation' => [...$ifPresent, 'in:lanjut,ragu,tidak'],
        ];
    }

    /**
     * Peserta tidak hadir → tidak ada penilaian, kosongkan skor & rekomendasi.
     */
    private function normalize(array $data): array
    {
        if (($data['attendance'] ?? null) === 'tidak_hadir') {
            foreach ([
                'score_enthusiasm', 'score_understanding', 'score_focus', 'score_collaboration',
                'recommended_level', 'recommendation',
            ] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
