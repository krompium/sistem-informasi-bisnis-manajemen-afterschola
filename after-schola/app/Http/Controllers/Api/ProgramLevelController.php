<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProgramLevelRequest;
use App\Http\Resources\ProgramLevelResource;
use App\Models\Program;
use App\Models\ProgramLevel;

class ProgramLevelController extends Controller
{
    public function index(Program $program)
    {
        return ProgramLevelResource::collection($program->levels);
    }

    public function store(SaveProgramLevelRequest $request, Program $program)
    {
        $level = $program->levels()->create($request->safe()->merge([
            'sort_order' => $request->integer('sort_order'),
        ])->all());

        return (new ProgramLevelResource($level))->response()->setStatusCode(201);
    }

    public function update(SaveProgramLevelRequest $request, Program $program, ProgramLevel $level)
    {
        $level->update($request->safe()->merge([
            'sort_order' => $request->integer('sort_order', $level->sort_order),
        ])->all());

        return new ProgramLevelResource($level);
    }

    // TODO(nanti): tolak bila level sudah dipakai class_groups (Modul 6) — FK restrict di sana.
    public function destroy(Program $program, ProgramLevel $level)
    {
        $level->delete();

        return response()->noContent();
    }
}
