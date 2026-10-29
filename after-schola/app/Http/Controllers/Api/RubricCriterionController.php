<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveRubricCriterionRequest;
use App\Http\Resources\RubricCriterionResource;
use App\Models\Program;
use App\Models\RubricCriterion;

class RubricCriterionController extends Controller
{
    public function index(Program $program)
    {
        return RubricCriterionResource::collection($program->rubricCriteria);
    }

    public function store(SaveRubricCriterionRequest $request, Program $program)
    {
        $criterion = $program->rubricCriteria()->create($request->validated());

        return (new RubricCriterionResource($criterion))->response()->setStatusCode(201);
    }

    public function update(SaveRubricCriterionRequest $request, Program $program, RubricCriterion $rubricCriterion)
    {
        $rubricCriterion->update($request->validated());

        return new RubricCriterionResource($rubricCriterion);
    }

    // TODO(nanti): tolak bila kriteria sudah punya skor evaluasi (Fase 4) — FK restrict di evaluation_scores.
    public function destroy(Program $program, RubricCriterion $rubricCriterion)
    {
        $rubricCriterion->delete();

        return response()->noContent();
    }
}
