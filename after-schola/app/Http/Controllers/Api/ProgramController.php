<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    public function index()
    {
        return ProgramResource::collection(Program::with(['levels', 'rubricCriteria'])->latest()->get());
    }

    public function store(StoreProgramRequest $request)
    {
        $program = Program::create($request->validated());

        return (new ProgramResource($program->load(['levels', 'rubricCriteria'])))->response()->setStatusCode(201);
    }

    public function show(Program $program)
    {
        return new ProgramResource($program->load(['levels', 'rubricCriteria']));
    }

    public function update(UpdateProgramRequest $request, Program $program)
    {
        $program->update($request->validated());

        return new ProgramResource($program->load(['levels', 'rubricCriteria']));
    }

    public function destroy(Program $program)
    {
        if ($program->isInUse()) {
            throw ValidationException::withMessages([
                'program' => ['Program sudah dipakai, nonaktifkan saja.'],
            ]);
        }

        $program->delete();

        return response()->noContent();
    }

    // Publik (PUB-01, PUB-02). Tanpa rubrik. Slot trial ada di /public/trial-slots.
    // TODO(nanti): cabang tersedia per program (PUB-01) — kini turunan dari slot trial.
    public function publicIndex()
    {
        return ProgramResource::collection(
            Program::where('is_active', true)->with(['levels'])->latest()->get()
        );
    }

    public function publicShow(Program $program)
    {
        abort_unless($program->is_active, 404);

        return new ProgramResource($program->load(['levels']));
    }
}
