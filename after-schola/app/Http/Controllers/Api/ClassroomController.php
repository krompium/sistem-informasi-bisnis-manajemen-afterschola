<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Classroom::query()->withCount('students')->with('school');

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }

        if (! $user->hasAnyRole(['management', 'finance', 'hr'])) {
            $query->whereIn('school_id', $user->assignedSchoolIds());
        }

        return ClassroomResource::collection($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('create', Classroom::class);

        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:255'],
        ]);

        $classroom = Classroom::create($data);

        return (new ClassroomResource($classroom))->response()->setStatusCode(201);
    }

    public function show(Classroom $classroom)
    {
        $this->authorize('view', $classroom);

        return new ClassroomResource($classroom->load('school')->loadCount('students'));
    }

    public function update(Request $request, Classroom $classroom)
    {
        $this->authorize('update', $classroom);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:255'],
        ]);

        $classroom->update($data);

        return new ClassroomResource($classroom);
    }

    public function destroy(Classroom $classroom)
    {
        $this->authorize('delete', $classroom);

        $classroom->delete();

        return response()->json(['message' => 'Level dihapus.']);
    }
}
