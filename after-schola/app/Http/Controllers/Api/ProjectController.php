<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        return ProjectResource::collection(
            Project::visibleTo($request->user())
                ->when($request->query('team_id'), fn ($q, $teamId) => $q->where('team_id', $teamId))
                ->orderBy('name')
                ->paginate()
        );
    }

    public function store(StoreProjectRequest $request)
    {
        return new ProjectResource(Project::create($request->validated()));
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());

        return new ProjectResource($project);
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        // TODO(nanti): Q5 masih default (ditolak selama berisi task) — sesuaikan bila jawaban resmi beda.
        if ($project->tasks()->exists()) {
            throw ValidationException::withMessages([
                'project' => ['Proyek masih memiliki task. Hapus task-nya terlebih dahulu.'],
            ]);
        }

        $project->delete();

        return response()->noContent();
    }
}
