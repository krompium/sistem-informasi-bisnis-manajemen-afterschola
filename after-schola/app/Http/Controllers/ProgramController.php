<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::with('jadwalTrial')->latest()->get();

        return ProgramResource::collection($programs);
    }

    public function store(StoreProgramRequest $request)
    {
        $program = Program::create($request->validated());

        return new ProgramResource($program);
    }

    public function show(Program $program)
    {
        $program->load('jadwalTrial');

        return new ProgramResource($program);
    }

    public function update(UpdateProgramRequest $request, Program $program)
    {
        $program->update($request->validated());

        return new ProgramResource($program);
    }

    public function destroy(Program $program)
    {
        $program->delete();

        return response()->json(['message' => 'Program berhasil dihapus']);
    }

    public function indexAktif()
        {
            $programs = Program::where('status_aktif', true)
                ->with('jadwalTrial')
                ->latest()
                ->get();

            return ProgramResource::collection($programs);
        }
}