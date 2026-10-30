<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTrialSlotRequest;
use App\Http\Resources\PublicTrialSlotResource;
use App\Http\Resources\TrialSlotResource;
use App\Models\Branch;
use App\Models\Program;
use App\Models\TrialSlot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CRUD slot trial (permission: manage trial slots; scope cabang lewat query dan Policy).
 */
class TrialSlotController extends Controller
{
    private const WITH = ['branch:id,name', 'program:id,name', 'trainer:id,name'];

    public function index(Request $request)
    {
        $query = TrialSlot::visibleTo($request->user())->with(self::WITH)->withCount('leads');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }

        if ($request->query('period') === 'upcoming') {
            $query->where('starts_at', '>=', now())->orderBy('starts_at');
        } elseif ($request->query('period') === 'past') {
            $query->where('starts_at', '<', now())->orderByDesc('starts_at');
        } else {
            $query->orderByDesc('starts_at');
        }

        return TrialSlotResource::collection($query->paginate(25));
    }

    // Publik (PUB-02): slot yang masih bisa dipesan untuk satu program. Tanpa data trainer.
    public function publicIndex(Request $request)
    {
        $request->validate([
            'program_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $query = TrialSlot::bookable()
            ->where('program_id', $request->integer('program_id'))
            ->with(['branch:id,name', 'program:id,name'])
            ->withCount('leads')
            ->orderBy('starts_at');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        return PublicTrialSlotResource::collection($query->paginate(50));
    }

    // Pilihan untuk form slot. Sengaja di sini: Marketing tidak punya akses ke /branches, /programs, /users.
    public function options(Request $request)
    {
        $branches = Branch::visibleTo($request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $branchIds = $branches->pluck('id');

        $trainers = User::role('trainer')->where('is_active', true)->with('branches:id')->orderBy('name')->get()
            ->map(fn (User $trainer) => [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'branch_ids' => ($trainer->hasAllBranches() ? $branchIds : $trainer->branches->pluck('id')->intersect($branchIds))->values()->all(),
            ])
            ->filter(fn (array $trainer) => $trainer['branch_ids'] !== [])
            ->values();

        return response()->json(['data' => [
            'branches' => $branches,
            'programs' => Program::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'trainers' => $trainers,
        ]]);
    }

    public function store(SaveTrialSlotRequest $request)
    {
        $slot = TrialSlot::create($request->validated());

        return (new TrialSlotResource($slot->load(self::WITH)->loadCount('leads')))->response()->setStatusCode(201);
    }

    public function show(TrialSlot $trialSlot)
    {
        $this->authorize('view', $trialSlot);

        return new TrialSlotResource($trialSlot->load(self::WITH)->loadCount('leads'));
    }

    public function update(SaveTrialSlotRequest $request, TrialSlot $trialSlot)
    {
        $trialSlot->update($request->validated());

        return new TrialSlotResource($trialSlot->load(self::WITH)->loadCount('leads'));
    }

    public function destroy(TrialSlot $trialSlot)
    {
        $this->authorize('delete', $trialSlot);

        if ($trialSlot->leads()->exists()) {
            throw ValidationException::withMessages(['slot' => ['Slot sudah punya peserta. Tutup pendaftaran slot ini, jangan dihapus.']]);
        }

        $trialSlot->delete();

        return response()->noContent();
    }
}
