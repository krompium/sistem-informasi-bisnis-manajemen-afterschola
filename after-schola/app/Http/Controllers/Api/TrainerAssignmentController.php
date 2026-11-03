<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTrainerAssignmentRequest;
use App\Http\Resources\TrainerAssignmentResource;
use App\Models\TrainerAssignment;

/** Penugasan trainer ke kelas (Modul 6a, MGT-33). Permission assign trainers di route; scope cabang di FormRequest/Policy. */
class TrainerAssignmentController extends Controller
{
    public function store(SaveTrainerAssignmentRequest $request)
    {
        $assignment = TrainerAssignment::create($request->validated());

        return (new TrainerAssignmentResource($assignment->load('trainer:id,name')))->response()->setStatusCode(201);
    }

    public function update(SaveTrainerAssignmentRequest $request, TrainerAssignment $trainerAssignment)
    {
        $trainerAssignment->update($request->validated());

        return new TrainerAssignmentResource($trainerAssignment->load('trainer:id,name'));
    }

    public function destroy(TrainerAssignment $trainerAssignment)
    {
        $this->authorize('view', $trainerAssignment->classGroup);

        $trainerAssignment->delete();

        return response()->noContent();
    }
}
