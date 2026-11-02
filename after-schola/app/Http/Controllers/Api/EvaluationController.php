<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEvaluationRequest;
use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Evaluasi periodik (Modul 9a, TRN-09/TRN-10). Baca: trainer (miliknya) dan peninjau (per cabang), dibatasi
 * Evaluation::visibleTo. Tulis: role trainer di route, trainer yang ditugaskan di kelas siswa.
 */
class EvaluationController extends Controller
{
    public const DETAIL = ['enrollment.student:id,name', 'enrollment.classGroup.program.rubricCriteria', 'evaluator:id,name', 'report', 'criteria'];

    public function index(Request $request)
    {
        $query = Evaluation::visibleTo($request->user())
            ->with(['enrollment.student:id,name', 'enrollment.classGroup:id,name', 'evaluator:id,name', 'report'])
            ->latest('id');

        if ($request->filled('enrollment_id')) {
            $query->where('enrollment_id', $request->integer('enrollment_id'));
        }
        if ($request->filled('class_group_id')) {
            $query->whereHas('enrollment', fn ($e) => $e->where('class_group_id', $request->integer('class_group_id')));
        }
        if ($request->filled('status')) {
            $query->whereHas('report', fn ($r) => $r->where('status', $request->input('status')));
        }

        return EvaluationResource::collection($query->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function show(Evaluation $evaluation)
    {
        $this->authorize('view', $evaluation);

        return new EvaluationResource($evaluation->load(self::DETAIL));
    }

    public function store(SaveEvaluationRequest $request)
    {
        $evaluation = DB::transaction(function () use ($request) {
            $evaluation = Evaluation::create($request->validated() + ['evaluator_id' => $request->user()->id]);
            $evaluation->report()->create(['status' => 'draft']);

            return $evaluation;
        });

        return (new EvaluationResource($evaluation->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    public function update(SaveEvaluationRequest $request, Evaluation $evaluation)
    {
        if ($evaluation->report->status !== 'draft') {
            throw ValidationException::withMessages(['status' => ['Evaluasi sudah diajukan dan tidak dapat diubah.']]);
        }

        $data = $request->validated();
        DB::transaction(function () use ($data, $evaluation) {
            if (array_key_exists('summary', $data)) {
                $evaluation->update(['summary' => $data['summary']]);
            }
            $scores = collect($data['scores'] ?? [])->mapWithKeys(fn ($row) => [$row['criterion_id'] => ['score' => $row['score']]]);
            if ($scores->isNotEmpty()) {
                $evaluation->criteria()->syncWithoutDetaching($scores->all());
            }
        });

        return new EvaluationResource($evaluation->load(self::DETAIL));
    }
}
