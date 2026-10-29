<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTrialSlotRequest;
use App\Http\Resources\TrialSlotResource;
use App\Models\Branch;
use App\Models\Program;
use App\Models\TrialSlot;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * CRUD slot trial (permission: manage trial slots; scope cabang lewat query dan Policy).
 */
class TrialSlotController extends Controller
{
    private const WITH = ['branch:id,name', 'program:id,name', 'trainer:id,name'];

    public function index(Request $request)
    {
        $query = TrialSlot::visibleTo($request->user())->with(self::WITH);

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

        return (new TrialSlotResource($slot->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(TrialSlot $trialSlot)
    {
        $this->authorize('view', $trialSlot);

        return new TrialSlotResource($trialSlot->load(self::WITH));
    }

    public function update(SaveTrialSlotRequest $request, TrialSlot $trialSlot)
    {
        $trialSlot->update($request->validated());

        return new TrialSlotResource($trialSlot->load(self::WITH));
    }

    public function destroy(TrialSlot $trialSlot)
    {
        $this->authorize('delete', $trialSlot);

        $trialSlot->delete();

        return response()->noContent();
    }
}
