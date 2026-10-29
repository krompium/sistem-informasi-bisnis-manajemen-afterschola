<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Models\ProgramTrialSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    public function index()
    {
        return ProgramResource::collection(Program::with(['jadwalTrial', 'levels', 'rubricCriteria'])->latest()->get());
    }

    public function store(StoreProgramRequest $request)
    {
        $program = Program::create($request->validated());

        return (new ProgramResource($program->load(['jadwalTrial', 'levels', 'rubricCriteria'])))->response()->setStatusCode(201);
    }

    public function show(Program $program)
    {
        return new ProgramResource($program->load(['jadwalTrial', 'levels', 'rubricCriteria']));
    }

    public function update(UpdateProgramRequest $request, Program $program)
    {
        $program->update($request->validated());

        return new ProgramResource($program->load(['jadwalTrial', 'levels', 'rubricCriteria']));
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

    // Publik (PUB-01, PUB-02). Tanpa rubrik. TODO(nanti): cabang tersedia (cabang dari Modul 0) dan
    // slot trial berkapasitas (Modul 2); sementara jadwal_trial mingguan.
    public function publicIndex()
    {
        return ProgramResource::collection(
            Program::where('is_active', true)->with(['jadwalTrial', 'levels'])->latest()->get()
        );
    }

    public function publicShow(Program $program)
    {
        abort_unless($program->is_active, 404);

        return new ProgramResource($program->load(['jadwalTrial', 'levels']));
    }

    public function storeJadwalTrial(Request $request, Program $program)
    {
        $validated = $request->validate([
            'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
        ]);

        $jadwal = $program->jadwalTrial()->create($validated);

        return response()->json(['data' => $jadwal], 201);
    }

    public function destroyJadwalTrial(Program $program, ProgramTrialSchedule $jadwal)
    {
        $jadwal->delete();

        return response()->noContent();
    }
}
