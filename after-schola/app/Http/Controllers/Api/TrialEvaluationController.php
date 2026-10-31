<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTrialEvaluationRequest;
use App\Http\Resources\TrialEvaluationResource;
use App\Models\Lead;
use App\Models\TrialEvaluation;
use Illuminate\Http\Request;

/**
 * Evaluasi trial per peserta slot (Modul 3). Scope: staf per cabang, trainer hanya slot miliknya (Policy + query).
 */
class TrialEvaluationController extends Controller
{
    private const WITH = ['trainer:id,name', 'level:id,name'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', TrialEvaluation::class);

        $query = TrialEvaluation::visibleTo($request->user())->with(self::WITH);

        if ($request->filled('q')) {
            $query->where('participant_name', 'like', '%'.$request->string('q').'%');
        }
        if ($request->filled('recommendation')) {
            $query->where('recommendation', $request->string('recommendation'));
        }
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->integer('lead_id'));
        }

        // TODO(nanti): paginasi (NFR-02) — volume per cabang masih kecil; jadikan paginate() bersama view-nya.
        return TrialEvaluationResource::collection(
            $query->orderByDesc('trial_date')->orderByDesc('id')->get()
        );
    }

    public function store(SaveTrialEvaluationRequest $request)
    {
        // Lead sudah lolos validasi (punya slot yang boleh dinilai), jadi identitas, cabang, dan trainer
        // diambil dari lead dan slot, bukan dari klien.
        $lead = Lead::with('trialSlot')->findOrFail($request->integer('lead_id'));
        $slot = $lead->trialSlot;

        $evaluation = TrialEvaluation::create($this->normalize($request->validated()) + [
            'branch_id' => $slot->branch_id,
            'trial_slot_id' => $slot->id,
            'program_id' => $slot->program_id,
            'trainer_id' => $slot->trainer_id,
            'trial_date' => $slot->starts_at->timezone(config('app.display_timezone'))->toDateString(),
            'participant_name' => $lead->name,
            'origin_school' => $lead->origin_school,
        ]);

        return (new TrialEvaluationResource($evaluation->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(TrialEvaluation $trialEvaluation)
    {
        $this->authorize('view', $trialEvaluation);

        return new TrialEvaluationResource($trialEvaluation->load(self::WITH));
    }

    public function update(SaveTrialEvaluationRequest $request, TrialEvaluation $trialEvaluation)
    {
        $trialEvaluation->update($this->normalize($request->validated()));

        return new TrialEvaluationResource($trialEvaluation->load(self::WITH));
    }

    public function destroy(TrialEvaluation $trialEvaluation)
    {
        $this->authorize('delete', $trialEvaluation);

        $trialEvaluation->delete();

        return response()->json(['message' => 'Evaluasi trial dihapus.']);
    }

    /**
     * Peserta tidak hadir → tidak ada penilaian, kosongkan skor, level, dan rekomendasi.
     */
    private function normalize(array $data): array
    {
        if (($data['attendance'] ?? null) === 'tidak_hadir') {
            foreach ([
                'score_enthusiasm', 'score_understanding', 'score_focus', 'score_collaboration',
                'recommended_level_id', 'recommendation',
            ] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
