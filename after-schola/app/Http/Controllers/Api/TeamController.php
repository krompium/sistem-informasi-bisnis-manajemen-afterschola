<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Team::class);

        return TeamResource::collection(
            Team::visibleTo($request->user())
                ->with('members')
                ->withCount('projects')
                ->orderBy('name')
                ->paginate()
        );
    }

    public function store(StoreTeamRequest $request)
    {
        $team = Team::create($request->safe()->only(['name', 'description']));
        $team->members()->sync($this->memberPayload($request->validated('members') ?? []));

        return new TeamResource($team->load('members'));
    }

    public function show(Team $team)
    {
        $this->authorize('view', $team);

        return new TeamResource($team->load('members')->loadCount('projects'));
    }

    public function update(UpdateTeamRequest $request, Team $team)
    {
        $team->update($request->safe()->only(['name', 'description']));

        if ($request->has('members')) {
            $team->members()->sync($this->memberPayload($request->validated('members')));
        }

        return new TeamResource($team->load('members')->loadCount('projects'));
    }

    public function destroy(Team $team)
    {
        $this->authorize('delete', $team);

        // TODO(nanti): Q5 masih default (ditolak selama berisi proyek) — sesuaikan bila jawaban resmi beda.
        if ($team->projects()->exists()) {
            throw ValidationException::withMessages([
                'team' => ['Team masih memiliki proyek. Hapus proyeknya terlebih dahulu.'],
            ]);
        }

        $team->delete();

        return response()->noContent();
    }

    /** [{user_id, role_in_team}, ...] -> format sync() */
    private function memberPayload(array $members): array
    {
        return collect($members)
            ->mapWithKeys(fn ($m) => [$m['user_id'] => ['role_in_team' => $m['role_in_team']]])
            ->all();
    }
}
