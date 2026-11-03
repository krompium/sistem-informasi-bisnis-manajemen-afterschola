<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveClassGroupRequest;
use App\Http\Resources\ClassGroupResource;
use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Kelas reguler (Modul 6a, MGT-30). Baca: staf per cabang dan trainer per penugasan (Policy + query).
 * Tulis: permission manage classes di route. Tidak ada DELETE: kelas diarsipkan lewat is_active.
 */
class ClassGroupController extends Controller
{
    private const WITH = ['program:id,name', 'level:id,name', 'branch:id,name', 'assignments.trainer:id,name'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', ClassGroup::class);

        $query = ClassGroup::visibleTo($request->user())->with(self::WITH)->withCount('activeEnrollments')->orderBy('name');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ClassGroupResource::collection($query->paginate(25));
    }

    // Pilihan untuk form kelas dan penugasan. Sengaja di sini: staf kelas tidak punya akses ke /branches, /programs, /users.
    public function options(Request $request)
    {
        $branches = Branch::visibleTo($request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $branchIds = $branches->pluck('id');

        $trainers = User::trainerOptions($branchIds);

        $programs = Program::where('is_active', true)->orderBy('name')
            ->with(['levels' => fn ($q) => $q->orderBy('sort_order')->select('id', 'program_id', 'name')])
            ->get(['id', 'name'])
            ->map(fn (Program $program) => [
                'id' => $program->id,
                'name' => $program->name,
                'levels' => $program->levels->map(fn ($level) => ['id' => $level->id, 'name' => $level->name])->values(),
            ]);

        return response()->json(['data' => compact('branches', 'programs', 'trainers')]);
    }

    public function store(SaveClassGroupRequest $request)
    {
        $classGroup = ClassGroup::create($request->validated());

        return (new ClassGroupResource($classGroup->load(self::WITH)->loadCount('activeEnrollments')))->response()->setStatusCode(201);
    }

    public function show(ClassGroup $classGroup)
    {
        $this->authorize('viewAny', ClassGroup::class);
        $this->authorize('view', $classGroup);

        return new ClassGroupResource($classGroup->load(self::WITH)->loadCount('activeEnrollments'));
    }

    public function update(SaveClassGroupRequest $request, ClassGroup $classGroup)
    {
        $classGroup->update($request->validated());

        return new ClassGroupResource($classGroup->load(self::WITH)->loadCount('activeEnrollments'));
    }
}
